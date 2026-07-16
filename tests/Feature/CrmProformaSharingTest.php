<?php

namespace Tests\Feature;

use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\ProformaStatus;
use App\Enums\UserRole;
use App\Mail\CrmProformaShareMail;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class CrmProformaSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_prepare_whatsapp_share_and_missing_phone_keeps_a_copyable_message(): void
    {
        $manager = $this->user(UserRole::Manager);
        $proforma = $this->proforma($manager);

        $this->actingAs($manager)->get("/crm/proforma-invoices/{$proforma->id}/whatsapp")
            ->assertRedirect("/crm/proforma-invoices/{$proforma->id}")
            ->assertSessionHas('whatsappPayload', fn (array $payload): bool => str_starts_with($payload['url'], 'https://wa.me/919000011111?text='));

        $this->assertDatabaseHas('crm_proforma_shares', [
            'proforma_invoice_id' => $proforma->id,
            'channel' => 'whatsapp',
            'recipient' => '919000011111',
            'status' => 'prepared',
        ]);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $proforma->lead_id, 'subject' => 'Proforma WhatsApp share prepared.']);

        $missingPhone = $this->proforma($manager, ['customer_phone' => null]);
        $this->actingAs($manager)->get("/crm/proforma-invoices/{$missingPhone->id}/whatsapp")
            ->assertRedirect("/crm/proforma-invoices/{$missingPhone->id}")
            ->assertSessionHas('whatsappPayload', fn (array $payload): bool => $payload['url'] === null && str_contains($payload['message'], $missingPhone->proforma_number));
        $this->assertDatabaseHas('crm_proforma_shares', ['proforma_invoice_id' => $missingPhone->id, 'channel' => 'whatsapp', 'recipient' => null, 'status' => 'prepared']);
    }

    public function test_email_form_and_successful_delivery_mark_a_draft_as_sent_and_record_history(): void
    {
        Mail::fake();
        $manager = $this->user(UserRole::Manager);
        $proforma = $this->proforma($manager);

        $this->actingAs($manager)->get("/crm/proforma-invoices/{$proforma->id}/email/create")
            ->assertOk()
            ->assertSee('Send proforma by email')
            ->assertSee($proforma->customer_email);

        $this->actingAs($manager)->post("/crm/proforma-invoices/{$proforma->id}/email/send", $this->emailPayload())
            ->assertRedirect("/crm/proforma-invoices/{$proforma->id}");

        Mail::assertSent(CrmProformaShareMail::class, fn (CrmProformaShareMail $mail): bool => $mail->hasTo('asha@example.test') && $mail->emailSubject === 'RetailPOS Proforma Invoice - Custom');
        $this->assertSame(ProformaStatus::Sent, $proforma->refresh()->status);
        $this->assertNotNull($proforma->sent_at);
        $this->assertDatabaseHas('crm_proforma_shares', ['proforma_invoice_id' => $proforma->id, 'channel' => 'email', 'recipient' => 'asha@example.test', 'status' => 'sent']);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $proforma->lead_id, 'subject' => "Proforma invoice {$proforma->proforma_number} sent by email to asha@example.test."]);
    }

    public function test_email_failure_records_history_and_notification_without_changing_status(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $proforma = $this->proforma($manager, [], $sales);
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Transport is unavailable'));

        $this->actingAs($manager)->post("/crm/proforma-invoices/{$proforma->id}/email/send", $this->emailPayload())
            ->assertRedirect("/crm/proforma-invoices/{$proforma->id}")
            ->assertSessionHas('error');

        $this->assertSame(ProformaStatus::Draft, $proforma->refresh()->status);
        $this->assertDatabaseHas('crm_proforma_shares', ['proforma_invoice_id' => $proforma->id, 'channel' => 'email', 'recipient' => 'asha@example.test', 'status' => 'failed']);
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.proforma.share_failed')->exists());
    }

    public function test_marking_a_draft_as_sent_does_not_downgrade_paid_proformas(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $draft = $this->proforma($manager, [], $sales);

        $this->actingAs($manager)->post("/crm/proforma-invoices/{$draft->id}/mark-sent")->assertRedirect();
        $this->assertSame(ProformaStatus::Sent, $draft->refresh()->status);
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.proforma.sent')->exists());

        $paid = $this->proforma($manager, ['status' => ProformaStatus::Paid, 'paid_amount' => 1180, 'balance_amount' => 0, 'paid_at' => now()], $sales);
        $this->actingAs($manager)->post("/crm/proforma-invoices/{$paid->id}/mark-sent")
            ->assertSessionHasErrors('proforma');
        $this->assertSame(ProformaStatus::Paid, $paid->refresh()->status);
    }

    public function test_payment_and_full_payment_notifications_are_dispatched_and_share_history_is_visible(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $proforma = $this->proforma($manager, [], $sales);

        $this->actingAs($manager)->post("/crm/proforma-invoices/{$proforma->id}/payments", ['amount' => 500, 'payment_date' => today()->toDateString(), 'payment_mode' => 'upi'])->assertRedirect();
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.proforma.payment_recorded')->exists());

        $this->actingAs($manager)->post("/crm/proforma-invoices/{$proforma->id}/payments", ['amount' => 680, 'payment_date' => today()->toDateString(), 'payment_mode' => 'upi'])->assertRedirect();
        $this->assertSame(ProformaStatus::Paid, $proforma->refresh()->status);
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.proforma.fully_paid')->exists());

        $this->actingAs($manager)->get("/crm/proforma-invoices/{$proforma->id}")
            ->assertOk()
            ->assertSee('Share history');
    }

    public function test_staff_cannot_access_proforma_sharing_actions(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);
        $proforma = $this->proforma($manager);

        $this->actingAs($staff)->get("/crm/proforma-invoices/{$proforma->id}/whatsapp")->assertForbidden();
        $this->actingAs($staff)->get("/crm/proforma-invoices/{$proforma->id}/email/create")->assertForbidden();
    }

    /** @param array<string, mixed> $overrides */
    private function proforma(User $user, array $overrides = [], ?User $assignedUser = null): CrmProformaInvoice
    {
        $lead = $this->lead($user, $assignedUser ?? $user);

        return CrmProformaInvoice::create(array_merge([
            'company_id' => $user->company_id,
            'lead_id' => $lead->id,
            'proforma_number' => 'RPI-'.now()->format('Y').'-'.str_pad((string) (CrmProformaInvoice::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'title' => 'RetailPOS Command Center',
            'customer_name' => 'Asha Mehta',
            'customer_company' => 'Demo Retail Group',
            'customer_email' => 'asha@example.test',
            'customer_phone' => '+91 90000 11111',
            'currency' => 'INR',
            'subtotal' => 1000,
            'tax_total' => 180,
            'grand_total' => 1180,
            'paid_amount' => 0,
            'balance_amount' => 1180,
            'invoice_date' => today(),
            'due_date' => today()->addWeek(),
            'status' => ProformaStatus::Draft,
            'internal_remarks' => 'Internal sales margin.',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $overrides));
    }

    private function lead(User $user, User $assignedUser): CrmLead
    {
        $source = CrmLeadSource::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'website-contact'], ['name' => 'Website Contact', 'is_active' => true]);
        $status = CrmLeadStatus::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'new'], ['name' => 'New', 'stage_type' => LeadStageType::New, 'is_active' => true, 'sort_order' => 1]);

        return CrmLead::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'source_id' => $source->id, 'status_id' => $status->id, 'assigned_user_id' => $assignedUser->id, 'created_by' => $user->id, 'title' => 'Enterprise Retail Discovery', 'business_name' => 'Demo Retail Group', 'contact_name' => 'Asha Mehta', 'email' => 'asha@example.test', 'phone' => '+91 90000 11111', 'currency' => 'INR', 'priority' => LeadPriority::Medium]);
    }

    /** @return array<string, mixed> */
    private function emailPayload(): array
    {
        return ['to_email' => 'asha@example.test', 'cc' => 'accounts@example.test; founder@example.test', 'subject' => 'RetailPOS Proforma Invoice - Custom', 'message_body' => "Hello Asha,\n\nYour proforma is ready.", 'attach_pdf' => false];
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
