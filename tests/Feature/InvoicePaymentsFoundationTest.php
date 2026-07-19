<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmQuotation;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Crm\InvoiceService;
use App\Services\Crm\PublicInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoicePaymentsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_quotation_converts_once_with_immutable_snapshots(): void
    {
        $manager = $this->user(UserRole::Manager);
        $quote = $this->quotation($manager);
        $quote->items()->create(['name' => 'RetailPOS setup', 'quantity' => 1, 'unit' => 'service', 'unit_price' => 1000, 'discount_amount' => 100, 'tax_rate' => 18, 'tax_amount' => 162, 'line_total' => 1062, 'sort_order' => 1]);

        $this->actingAs($manager)->post("/sales/quotations/{$quote->id}/invoices")->assertRedirect();
        $invoice = CrmInvoice::query()->firstOrFail();
        $this->assertSame($quote->id, $invoice->quotation_id);
        $this->assertSame('RPOS-INV-'.now()->format('Y').'-00001', $invoice->invoice_number);
        $this->assertSame('1062.00', $invoice->grand_total);
        $this->assertSame('RetailPOS setup', $invoice->items()->firstOrFail()->name);
        $this->actingAs($manager)->post("/sales/quotations/{$quote->id}/invoices")->assertSessionHasErrors('quotation');
    }

    public function test_partial_full_and_reversed_payment_recalculate_the_invoice_transactionally(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 1000);
        app(InvoiceService::class)->issue($invoice, $manager);
        $base = ['currency' => 'INR', 'payment_date' => today()->toDateString(), 'payment_method' => 'upi'];

        $this->actingAs($manager)->post("/sales/invoices/{$invoice->id}/payments", $base + ['amount' => 400, 'transaction_reference' => 'UPI-1'])->assertRedirect();
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->refresh()->status);
        $this->assertSame('600.00', $invoice->balance_due);
        $this->actingAs($manager)->post("/sales/invoices/{$invoice->id}/payments", $base + ['amount' => 601, 'transaction_reference' => 'UPI-2'])->assertSessionHasErrors('amount');
        $this->actingAs($manager)->post("/sales/invoices/{$invoice->id}/payments", $base + ['amount' => 600, 'transaction_reference' => 'UPI-3'])->assertRedirect();
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
        $payment = $invoice->payments()->latest('id')->firstOrFail();
        $this->actingAs($manager)->post("/sales/invoices/{$invoice->id}/payments/{$payment->id}/reverse", ['reason' => 'Bank reversal'])->assertRedirect();
        $this->assertSame('600.00', $invoice->refresh()->balance_due);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->refresh()->status);
    }

    public function test_pending_payment_does_not_reduce_balance_until_it_is_cleared(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 1000);
        app(InvoiceService::class)->issue($invoice, $manager);

        $payment = app(InvoiceService::class)->recordPayment($invoice, $manager, [
            'amount' => 400,
            'currency' => 'INR',
            'payment_date' => today()->toDateString(),
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
        ]);
        $this->assertSame('1000.00', $invoice->refresh()->balance_due);

        app(InvoiceService::class)->clearPayment($payment, $manager);
        $this->assertSame('600.00', $invoice->refresh()->balance_due);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->refresh()->status);
    }

    public function test_public_invoice_is_hashed_noindex_and_client_safe(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 2500);
        app(InvoiceService::class)->issue($invoice, $manager);
        $link = app(PublicInvoiceService::class)->issue($invoice, $manager);
        $token = basename(parse_url($link->url, PHP_URL_PATH));

        $this->assertSame(hash('sha256', $token), $invoice->refresh()->public_token_hash);
        $this->get('/i/'.$token)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('private internal note');
        $this->get('/i/no-such-invoice')->assertNotFound();
        $this->get('/i/'.$token.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

        app(PublicInvoiceService::class)->revoke($invoice, $manager);
        $this->get('/i/'.$token)->assertNotFound();
    }

    public function test_invoice_access_is_tenant_scoped_and_manual_reminders_do_not_require_smtp(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 2500);
        app(InvoiceService::class)->issue($invoice, $manager);

        $otherManager = $this->user(UserRole::Manager);
        $this->actingAs($otherManager)->get('/sales/invoices/'.$invoice->id)->assertNotFound();

        $this->actingAs($manager)
            ->post('/sales/invoices/'.$invoice->id.'/reminder', ['email' => 'asha@example.test'])
            ->assertRedirect();

        $delivery = NotificationDelivery::query()->where('company_id', $manager->company_id)->firstOrFail();
        $this->assertSame('email.invoice_reminder', $delivery->event_key);
        $this->assertSame('skipped_not_configured', $delivery->status);
    }

    public function test_draft_can_be_updated_but_issued_invoice_cannot_be_silently_changed(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 1000);

        $this->actingAs($manager)->post('/sales/invoices/'.$invoice->id, [
            '_method' => 'PUT',
            'billing_name' => 'Updated Asha',
            'currency' => 'INR',
            'items' => [['name' => 'Updated service', 'quantity' => 2, 'unit_price' => 500, 'tax_rate' => 0]],
        ])->assertRedirect('/sales/invoices/'.$invoice->id);
        $this->assertSame('Updated Asha', $invoice->refresh()->billing_name);
        $this->assertSame('Updated service', $invoice->items()->firstOrFail()->name);

        app(InvoiceService::class)->issue($invoice, $manager);
        $this->expectException(ValidationException::class);
        app(InvoiceService::class)->update($invoice, $manager, [
            'billing_name' => 'Should not apply',
            'currency' => 'INR',
            'items' => [['name' => 'Changed', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0]],
        ]);
    }

    public function test_authorized_manager_can_export_safe_invoice_csv(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 1000);

        $response = $this->actingAs($manager)->get('/sales/invoices/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertStreamed();

        $this->assertStringContainsString('Invoice number', $response->streamedContent());
        $this->assertStringContainsString($invoice->invoice_number, $response->streamedContent());
        $this->assertStringNotContainsString('private internal note', $response->streamedContent());
    }

    private function invoice(User $user, int $total): CrmInvoice
    {
        return app(InvoiceService::class)->create($user, ['billing_name' => 'Asha', 'billing_email' => 'asha@example.test', 'currency' => 'INR', 'internal_notes' => 'private internal note', 'items' => [['name' => 'RetailPOS', 'quantity' => 1, 'unit_price' => $total, 'tax_rate' => 0]]]);
    }

    private function quotation(User $user): CrmQuotation
    {
        $lead = $this->lead($user);
        return CrmQuotation::create(['company_id' => $user->company_id, 'lead_id' => $lead->id, 'quotation_number' => 'RPOS-'.now()->format('Y').'-00001', 'title' => 'Accepted proposal', 'currency' => 'INR', 'status' => QuotationStatus::Accepted, 'created_by' => $user->id]);
    }

    private function lead(User $user): CrmLead
    {
        $source = CrmLeadSource::create(['company_id' => $user->company_id, 'name' => 'Web', 'slug' => 'web', 'is_active' => true]);
        $status = CrmLeadStatus::create(['company_id' => $user->company_id, 'name' => 'New', 'slug' => 'new', 'stage_type' => LeadStageType::New, 'is_active' => true]);
        return CrmLead::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'source_id' => $source->id, 'status_id' => $status->id, 'assigned_user_id' => $user->id, 'created_by' => $user->id, 'title' => 'Retail rollout', 'priority' => LeadPriority::Medium]);
    }

    private function user(UserRole $role): User
    {
        $company = Company::factory()->create(); $branch = Branch::factory()->for($company)->create();
        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
