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
use App\Models\Crm\CrmInvoicePayment;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmQuotation;
use App\Models\NotificationDelivery;
use App\Models\Finance\CrmPaymentNumberSequence;
use App\Models\User;
use App\Services\Crm\InvoiceService;
use App\Services\Crm\InvoiceReceivableSummaryService;
use App\Services\Crm\PublicInvoiceService;
use App\Services\Finance\CrmPaymentNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        $this->assertSame('Partially Paid', app(InvoiceReceivableSummaryService::class)->forInvoice($invoice)['payment_status']);
        $this->actingAs($manager)->post("/sales/invoices/{$invoice->id}/payments", $base + ['amount' => 601, 'transaction_reference' => 'UPI-2'])->assertSessionHasErrors('amount');
        $this->actingAs($manager)->post("/sales/invoices/{$invoice->id}/payments", $base + ['amount' => 600, 'transaction_reference' => 'UPI-3'])->assertRedirect();
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
        $this->assertSame('Paid', app(InvoiceReceivableSummaryService::class)->forInvoice($invoice)['payment_status']);
        $payment = $invoice->payments()->latest('id')->firstOrFail();
        $this->actingAs($manager)->post("/sales/invoices/{$invoice->id}/payments/{$payment->id}/reverse", ['reason' => 'Bank reversal'])->assertRedirect();
        $this->assertSame('600.00', $invoice->refresh()->balance_due);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->refresh()->status);
        $this->assertSame('Partially Paid', app(InvoiceReceivableSummaryService::class)->forInvoice($invoice)['payment_status']);
    }

    public function test_legacy_issued_invoice_accepts_a_bank_transfer_and_refreshes_the_finance_ledger_once(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 10000);
        app(InvoiceService::class)->issue($invoice, $manager);

        // Mirrors an issued invoice that predates optional customer-finance linkage.
        $invoice->update(['customer_id' => null, 'amount_paid' => '0.00', 'credited_total' => '0.00', 'balance_due' => '10000.00']);

        $this->from("/sales/invoices/{$invoice->id}")->actingAs($manager)->post("/sales/invoices/{$invoice->id}/payments", [
            'amount' => '7000.00',
            'currency' => 'INR',
            'payment_date' => today()->toDateString(),
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'BANK-7000-LEGACY',
            'notes' => 'Recorded against a historical invoice.',
        ])->assertRedirect("/sales/invoices/{$invoice->id}")
            ->assertSessionHas('status', 'Payment recorded successfully. INR 7,000.00 received. Remaining balance: INR 3,000.00.');

        $payment = $invoice->payments()->firstOrFail();
        $this->assertSame('7000.00', $payment->amount);
        $this->assertSame('7000.00', $payment->allocated_amount);
        $this->assertSame('0.00', $payment->unallocated_amount);
        $this->assertNull($payment->customer_id);
        $this->assertDatabaseHas('crm_invoice_payment_allocations', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => '7000.00',
        ]);
        $this->assertSame('7000.00', $invoice->refresh()->amount_paid);
        $this->assertSame('3000.00', $invoice->refresh()->balance_due);

        $this->actingAs($manager)->get("/sales/invoices/{$invoice->id}")
            ->assertOk()
            ->assertSee('Payment &amp; Receivable Summary', false)
            ->assertSee('INR 7,000.00')
            ->assertSee('INR 3,000.00')
            ->assertSee('Partially Paid');

        $this->actingAs($manager)->post("/sales/invoices/{$invoice->id}/payments", [
            'amount' => '7000',
            'currency' => 'INR',
            'payment_date' => today()->toDateString(),
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'BANK-7000-LEGACY',
        ])->assertRedirect();

        $this->assertDatabaseCount('crm_invoice_payments', 1);
        $this->assertSame('3000.00', $invoice->refresh()->balance_due);
    }

    public function test_payment_on_an_invoice_with_a_pre_allocation_legacy_receipt_preserves_the_existing_receipt(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 10000);
        app(InvoiceService::class)->issue($invoice, $manager);

        CrmInvoicePayment::create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'invoice_id' => $invoice->id,
            'payment_reference' => 'LEGACY-PAYMENT-001',
            'receipt_number' => 'LEGACY-RECEIPT-001',
            'amount' => '3000.00',
            'allocated_amount' => '0.00',
            'unallocated_amount' => '0.00',
            'currency' => 'INR',
            'payment_date' => today()->subDay(),
            'payment_method' => 'bank_transfer',
            'status' => 'recorded',
            'recorded_by' => $manager->id,
            'idempotency_key' => hash('sha256', 'legacy-payment-'.$invoice->id),
        ]);

        $payment = app(InvoiceService::class)->recordPayment($invoice, $manager, [
            'amount' => '7000.00',
            'currency' => 'INR',
            'payment_date' => today()->toDateString(),
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'BANK-7000-WITH-LEGACY',
        ]);

        $this->assertSame('7000.00', $payment->amount);
        $this->assertSame('10000.00', $invoice->refresh()->amount_paid);
        $this->assertSame('0.00', $invoice->refresh()->balance_due);
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
        $this->assertDatabaseCount('crm_invoice_payments', 2);
        $this->assertDatabaseCount('crm_invoice_payment_allocations', 1);
    }

    public function test_global_receipt_allocator_reconciles_existing_production_receipts_before_recording_a_bank_transfer(): void
    {
        $firstManager = $this->user(UserRole::Manager);
        $firstInvoice = $this->invoice($firstManager, 1000);
        app(InvoiceService::class)->issue($firstInvoice, $firstManager);
        CrmInvoicePayment::create([
            'company_id' => $firstManager->company_id,
            'branch_id' => $firstManager->branch_id,
            'invoice_id' => $firstInvoice->id,
            'payment_reference' => 'RPOS-PAY-'.now()->format('Y').'-00001',
            'receipt_number' => 'RPOS-RCPT-'.now()->format('Y').'-00001',
            'amount' => '100.00', 'allocated_amount' => '0.00', 'unallocated_amount' => '0.00',
            'currency' => 'INR', 'payment_date' => today(), 'payment_method' => 'cash', 'status' => 'recorded',
            'recorded_by' => $firstManager->id, 'idempotency_key' => hash('sha256', 'historical-receipt-one'),
        ]);

        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 10000);
        app(InvoiceService::class)->issue($invoice, $manager);

        $payment = app(InvoiceService::class)->recordPayment($invoice, $manager, [
            'amount' => '7000.00', 'currency' => 'INR', 'payment_date' => today()->toDateString(),
            'payment_method' => 'bank_transfer', 'transaction_reference' => 'Advance Received',
        ]);

        $this->assertSame('RPOS-RCPT-'.now()->format('Y').'-00002', $payment->receipt_number);
        $this->assertSame('7000.00', $payment->amount);
        $this->assertSame('3000.00', $invoice->refresh()->balance_due);
        $this->assertDatabaseCount('crm_invoice_payment_allocations', 1);
    }

    public function test_shared_allocator_issues_unique_global_receipts_and_tenant_scoped_payment_references(): void
    {
        $first = $this->user(UserRole::Manager);
        $second = $this->user(UserRole::Manager);
        $numbers = app(CrmPaymentNumberService::class);

        DB::transaction(function () use ($numbers, $first, $second): void {
            $firstReceipt = $numbers->nextReceiptNumber();
            $secondReceipt = $numbers->nextReceiptNumber();
            $firstReference = $numbers->nextPaymentReference($first->company_id);
            $secondReference = $numbers->nextPaymentReference($second->company_id);

            $this->assertNotSame($firstReceipt, $secondReceipt);
            $this->assertSame('RPOS-PAY-'.now()->format('Y').'-00001', $firstReference);
            $this->assertSame('RPOS-PAY-'.now()->format('Y').'-00001', $secondReference);
        });

        $this->assertDatabaseCount('crm_payment_number_sequences', 3);
        $this->assertDatabaseHas('crm_payment_number_sequences', [
            'scope_key' => 'global',
            'sequence_type' => 'receipt_number',
            'calendar_year' => (int) now()->format('Y'),
            'last_sequence' => 2,
        ]);
    }

    public function test_global_receipt_allocator_reconciles_multiple_historical_values_and_stale_sequence_state(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 1000);
        app(InvoiceService::class)->issue($invoice, $manager);
        $year = (int) now()->format('Y');

        foreach ([1, 2, 7] as $sequence) {
            CrmInvoicePayment::create([
                'company_id' => $manager->company_id,
                'branch_id' => $manager->branch_id,
                'invoice_id' => $invoice->id,
                'payment_reference' => sprintf('LEGACY-PAY-%05d', $sequence),
                'receipt_number' => sprintf('RPOS-RCPT-%d-%05d', $year, $sequence),
                'amount' => '1.00', 'allocated_amount' => '0.00', 'unallocated_amount' => '0.00',
                'currency' => 'INR', 'payment_date' => today(), 'payment_method' => 'cash', 'status' => 'recorded',
                'recorded_by' => $manager->id, 'idempotency_key' => hash('sha256', 'historical-receipt-'.$sequence),
            ]);
        }

        CrmPaymentNumberSequence::create([
            'scope_key' => 'global', 'sequence_type' => 'receipt_number', 'calendar_year' => $year, 'last_sequence' => 2,
        ]);

        $receipt = app(CrmPaymentNumberService::class)->nextReceiptNumber($year);

        $this->assertSame(sprintf('RPOS-RCPT-%d-00008', $year), $receipt);
        $this->assertDatabaseHas('crm_payment_number_sequences', [
            'scope_key' => 'global', 'sequence_type' => 'receipt_number', 'calendar_year' => $year, 'last_sequence' => 8,
        ]);
    }

    public function test_payment_transaction_rolls_back_number_allocation_when_the_outer_finance_operation_fails(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 10000);
        app(InvoiceService::class)->issue($invoice, $manager);

        try {
            DB::transaction(function () use ($invoice, $manager): void {
                app(InvoiceService::class)->recordPayment($invoice, $manager, [
                    'amount' => '7000.00', 'currency' => 'INR', 'payment_date' => today()->toDateString(),
                    'payment_method' => 'bank_transfer', 'transaction_reference' => 'ROLLBACK-7000',
                ]);
                throw new \RuntimeException('Simulated downstream finance failure.');
            });
            $this->fail('The outer finance transaction should have rolled back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated downstream finance failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('crm_invoice_payments', 0);
        $this->assertDatabaseCount('crm_invoice_payment_allocations', 0);
        $this->assertDatabaseCount('crm_payment_number_sequences', 0);
        $this->assertSame('10000.00', $invoice->refresh()->balance_due);
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

    public function test_payment_retries_are_decimal_normalized_and_leave_invoice_totals_unchanged(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 1000);
        app(InvoiceService::class)->issue($invoice, $manager);
        $totals = $invoice->refresh()->only(['subtotal', 'discount_total', 'tax_total', 'grand_total']);
        $payload = ['amount' => '400', 'currency' => 'INR', 'payment_date' => today()->toDateString(), 'payment_method' => 'upi', 'transaction_reference' => 'UPI-RETRY'];

        $payment = app(InvoiceService::class)->recordPayment($invoice, $manager, $payload);
        $retry = app(InvoiceService::class)->recordPayment($invoice, $manager, array_replace($payload, ['amount' => '400.00']));

        $this->assertSame($payment->id, $retry->id);
        $this->assertDatabaseCount('crm_invoice_payments', 1);
        $this->assertSame('400.00', $invoice->refresh()->amount_paid);
        $this->assertSame('600.00', $invoice->balance_due);
        $this->assertSame($totals, $invoice->only(array_keys($totals)));
    }

    public function test_payment_service_rejects_invalid_amounts_and_cross_tenant_payment_access(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 1000);
        app(InvoiceService::class)->issue($invoice, $manager);
        $service = app(InvoiceService::class);

        foreach (['0', '-1', '1000.01'] as $amount) {
            try {
                $service->recordPayment($invoice, $manager, ['amount' => $amount, 'currency' => 'INR', 'payment_date' => today()->toDateString(), 'payment_method' => 'cash', 'transaction_reference' => 'INVALID-'.$amount]);
                $this->fail('An invalid payment amount was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('amount', $exception->errors());
            }
        }
        $this->assertDatabaseCount('crm_invoice_payments', 0);

        $payment = $service->recordPayment($invoice, $manager, ['amount' => '100', 'currency' => 'INR', 'payment_date' => today()->toDateString(), 'payment_method' => 'bank_transfer', 'transaction_reference' => 'PENDING-1', 'status' => 'pending']);
        $otherManager = $this->user(UserRole::Manager);

        try {
            $service->clearPayment($payment, $otherManager);
            $this->fail('Another tenant was allowed to clear a payment.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseHas('crm_invoice_payments', ['id' => $payment->id, 'company_id' => $manager->company_id, 'status' => 'pending']);
            $this->assertSame('0.00', $invoice->refresh()->amount_paid);
            $this->assertSame('1000.00', $invoice->balance_due);
        }
    }

    public function test_payment_form_rejects_an_ambiguous_date_before_the_service_is_called(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->invoice($manager, 1000);
        app(InvoiceService::class)->issue($invoice, $manager);

        $this->actingAs($manager)->post("/sales/invoices/{$invoice->id}/payments", [
            'amount' => '700.00',
            'currency' => 'INR',
            'payment_date' => '28/08/2026',
            'payment_method' => 'bank_transfer',
        ])->assertSessionHasErrors('payment_date');

        $this->assertDatabaseCount('crm_invoice_payments', 0);
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
        $invoice->update(['due_date' => today()->addDays(3)]);
        app(InvoiceService::class)->issue($invoice, $manager);

        $otherManager = $this->user(UserRole::Manager);
        $this->actingAs($otherManager)->get('/sales/invoices/'.$invoice->id)->assertNotFound();

        $this->actingAs($manager)
            ->post('/sales/invoices/'.$invoice->id.'/reminder', ['stage' => 'due_soon'])
            ->assertRedirect();

        $delivery = NotificationDelivery::query()->where('company_id', $manager->company_id)->firstOrFail();
        $this->assertSame('email.invoice_reminder_due_soon', $delivery->event_key);
        $this->assertSame('manual', $delivery->reminder_source);
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
