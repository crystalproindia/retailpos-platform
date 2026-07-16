<?php

namespace Tests\Feature;

use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\QuotationStatus;
use App\Enums\UserRole;
use App\Mail\CrmQuotationShareMail;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmQuotation;
use App\Models\Crm\CrmQuotationShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class CrmQuotationSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_download_a_client_safe_quotation_pdf_and_history_is_recorded(): void
    {
        $manager = $this->user(UserRole::Manager);
        $quotation = $this->quotation($manager);

        $response = $this->actingAs($manager)->get("/crm/quotations/{$quotation->id}/pdf");

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertDatabaseHas('crm_quotation_shares', [
            'quotation_id' => $quotation->id,
            'channel' => 'pdf_download',
            'status' => 'downloaded',
            'created_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.quotation.pdf_downloaded', 'auditable_id' => $quotation->id]);

        $this->actingAs($manager)->get("/crm/quotations/{$quotation->id}")
            ->assertOk()
            ->assertSee('Download PDF')
            ->assertSee('Send by Email')
            ->assertSee('Share history');
    }

    public function test_unauthorized_user_cannot_download_a_quotation_pdf(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);
        $quotation = $this->quotation($manager);

        $this->actingAs($staff)->get("/crm/quotations/{$quotation->id}/pdf")->assertForbidden();
        $this->assertDatabaseCount('crm_quotation_shares', 0);
    }

    public function test_public_proposal_link_is_unguessable_and_never_exposes_internal_remarks(): void
    {
        $manager = $this->user(UserRole::Manager);
        $quotation = $this->quotation($manager, ['internal_remarks' => 'Internal commission and margin discussion.']);

        $this->actingAs($manager)->post("/crm/quotations/{$quotation->id}/public-link")->assertRedirect();
        $quotation->refresh();

        $this->get('/q/'.$quotation->public_token)
            ->assertOk()
            ->assertSee($quotation->quotation_number)
            ->assertDontSee('Internal commission and margin discussion.');
        $this->get('/q/not-a-real-proposal-token')->assertNotFound();

        $this->actingAs($manager)
            ->post("/crm/quotations/{$quotation->id}/public-link", ['regenerate' => 1])
            ->assertRedirect();
        $this->assertNotSame($quotation->public_token, $quotation->refresh()->public_token);
    }

    public function test_whatsapp_share_generates_a_safe_click_to_send_url_and_handles_missing_phone(): void
    {
        $manager = $this->user(UserRole::Manager);
        $quotation = $this->quotation($manager, ['customer_phone' => '+91 (90000) 11111']);

        $response = $this->actingAs($manager)->get("/crm/quotations/{$quotation->id}/whatsapp");
        $response->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/919000011111?text=', (string) $response->headers->get('Location'));
        $this->assertDatabaseHas('crm_quotation_shares', ['quotation_id' => $quotation->id, 'channel' => 'whatsapp', 'recipient' => '919000011111', 'status' => 'prepared']);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $quotation->lead_id, 'subject' => 'Quotation share message prepared for WhatsApp.']);

        $missingPhone = $this->quotation($manager, ['customer_phone' => null]);
        $this->actingAs($manager)
            ->get("/crm/quotations/{$missingPhone->id}/whatsapp")
            ->assertRedirect("/crm/quotations/{$missingPhone->id}")
            ->assertSessionHas('whatsappMessage');
        $this->assertDatabaseHas('crm_quotation_shares', ['quotation_id' => $missingPhone->id, 'channel' => 'whatsapp', 'recipient' => null, 'status' => 'prepared']);
    }

    public function test_email_form_loads_and_sending_marks_a_draft_quotation_as_sent_with_history(): void
    {
        Mail::fake();
        $manager = $this->user(UserRole::Manager);
        $quotation = $this->quotation($manager);

        $this->actingAs($manager)->get("/crm/quotations/{$quotation->id}/email/create")
            ->assertOk()
            ->assertSee('Send proposal by email')
            ->assertSee($quotation->customer_email);
        $this->assertNotNull($quotation->refresh()->public_url);

        $this->actingAs($manager)
            ->post("/crm/quotations/{$quotation->id}/email/send", $this->emailPayload())
            ->assertRedirect("/crm/quotations/{$quotation->id}");

        Mail::assertSent(CrmQuotationShareMail::class, function (CrmQuotationShareMail $mail): bool {
            return $mail->hasTo('asha@example.test') && $mail->emailSubject === 'RetailPOS Proposal - Custom';
        });
        $this->assertSame(QuotationStatus::Sent, $quotation->refresh()->status);
        $this->assertNotNull($quotation->sent_at);
        $this->assertDatabaseHas('crm_quotation_shares', ['quotation_id' => $quotation->id, 'channel' => 'email', 'recipient' => 'asha@example.test', 'status' => 'sent']);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $quotation->lead_id, 'subject' => "Quotation {$quotation->quotation_number} sent by email to asha@example.test."]);
    }

    public function test_email_failure_records_share_failure_without_changing_quotation_status(): void
    {
        $manager = $this->user(UserRole::Manager);
        $quotation = $this->quotation($manager);
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Transport is unavailable'));

        $this->actingAs($manager)
            ->post("/crm/quotations/{$quotation->id}/email/send", $this->emailPayload())
            ->assertRedirect("/crm/quotations/{$quotation->id}")
            ->assertSessionHas('error');

        $this->assertSame(QuotationStatus::Draft, $quotation->refresh()->status);
        $this->assertDatabaseHas('crm_quotation_shares', ['quotation_id' => $quotation->id, 'channel' => 'email', 'recipient' => 'asha@example.test', 'status' => 'failed']);
    }

    /** @param array<string, mixed> $overrides */
    private function quotation(User $user, array $overrides = []): CrmQuotation
    {
        $lead = $this->lead($user);
        $quotation = CrmQuotation::create(array_merge([
            'lead_id' => $lead->id,
            'company_id' => $user->company_id,
            'quotation_number' => 'RPQ-'.now()->format('Y').'-'.str_pad((string) (CrmQuotation::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'title' => 'RetailPOS Growth Proposal',
            'customer_name' => 'Asha Mehta',
            'customer_company' => 'Demo Retail Group',
            'customer_email' => 'asha@example.test',
            'customer_phone' => '+91 90000 11111',
            'billing_address' => 'Sector 18, Noida',
            'currency' => 'INR',
            'subtotal' => 2000,
            'discount_total' => 100,
            'tax_total' => 342,
            'grand_total' => 2242,
            'valid_until' => now()->addDays(14),
            'status' => QuotationStatus::Draft,
            'notes' => 'Implementation plan included.',
            'terms_conditions' => 'Taxes are calculated per line item.',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $overrides));
        $quotation->items()->create([
            'name' => 'Command Center setup',
            'description' => 'CRM and proposal configuration',
            'quantity' => 1,
            'unit_price' => 2000,
            'discount_amount' => 100,
            'tax_rate' => 18,
            'tax_amount' => 342,
            'line_total' => 2242,
            'sort_order' => 1,
        ]);

        return $quotation;
    }

    private function lead(User $user): CrmLead
    {
        $source = CrmLeadSource::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'website-contact'], ['name' => 'Website Contact', 'is_active' => true]);
        $status = CrmLeadStatus::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'new'], ['name' => 'New', 'stage_type' => LeadStageType::New, 'is_active' => true, 'sort_order' => 1]);

        return CrmLead::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'source_id' => $source->id,
            'status_id' => $status->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Enterprise Retail Discovery',
            'business_name' => 'Demo Retail Group',
            'contact_name' => 'Asha Mehta',
            'email' => 'asha@example.test',
            'phone' => '+91 90000 11111',
            'currency' => 'INR',
            'priority' => LeadPriority::Medium,
        ]);
    }

    /** @return array<string, mixed> */
    private function emailPayload(): array
    {
        return [
            'to_email' => 'asha@example.test',
            'cc' => 'accounts@example.test; founder@example.test',
            'subject' => 'RetailPOS Proposal - Custom',
            'message_body' => "Hello Asha,\n\nYour proposal is ready.",
            'attach_pdf' => false,
        ];
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
