<?php

namespace Tests\Feature;

use App\Enums\Crm\CrmCustomerStatus;
use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerPortalToken;
use App\Models\Crm\CrmCustomerPortalUser;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmOnboardingNote;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmQuotation;
use App\Models\Crm\CrmQuotationItem;
use App\Models\Crm\CrmSupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads_and_manager_can_create_a_copyable_portal_link(): void
    {
        $manager = $this->manager();
        $customer = $this->customer($manager);

        $this->get('/portal/login')->assertOk()->assertSee('secure access link');
        $this->actingAs($manager)->post("/crm/customers/{$customer->id}/portal-users", ['name' => 'Asha Mehta', 'email' => 'asha@example.test', 'phone' => '+91 90000 11111'])
            ->assertRedirect()->assertSessionHas('portalInviteUrl');

        $this->assertDatabaseHas('crm_customer_portal_users', ['customer_id' => $customer->id, 'email' => 'asha@example.test', 'status' => 'invited']);
        $this->assertDatabaseCount('crm_customer_portal_tokens', 1);
    }

    public function test_magic_token_authenticates_once_and_expired_or_suspended_access_fails(): void
    {
        $portalUser = $this->portalUser($this->customer($this->manager()));
        $this->token($portalUser, 'valid-token');

        $this->get('/portal/access/valid-token')->assertRedirect('/portal');
        $this->flushSession();
        $this->get('/portal/access/valid-token')->assertRedirect('/portal/login');

        $expiredUser = $this->portalUser($portalUser->customer, 'expired@example.test');
        $this->token($expiredUser, 'expired-token', now()->subMinute());
        $this->get('/portal/access/expired-token')->assertRedirect('/portal/login');

        $suspended = $this->portalUser($portalUser->customer, 'suspended@example.test', 'suspended');
        $this->token($suspended, 'suspended-token');
        $this->get('/portal/access/suspended-token')->assertRedirect('/portal/login');
    }

    public function test_portal_user_only_sees_own_customer_safe_quotation_data(): void
    {
        $manager = $this->manager();
        $customer = $this->customer($manager);
        $lead = $this->lead($manager, $customer);
        $quotation = CrmQuotation::create(['company_id' => $manager->company_id, 'lead_id' => $lead->id, 'quotation_number' => 'Q-001', 'title' => 'RetailPOS rollout', 'currency' => 'INR', 'grand_total' => 20000, 'status' => 'sent', 'internal_remarks' => 'Never show this to customer.']);
        CrmQuotationItem::create(['quotation_id' => $quotation->id, 'name' => 'RetailPOS plan', 'quantity' => 1, 'unit_price' => 20000, 'line_total' => 20000, 'sort_order' => 1]);
        $otherCustomer = $this->customer($manager, 'Other Retail');
        $otherLead = $this->lead($manager, $otherCustomer, 'Other lead');
        $otherQuotation = CrmQuotation::create(['company_id' => $manager->company_id, 'lead_id' => $otherLead->id, 'quotation_number' => 'Q-002', 'title' => 'Private proposal', 'currency' => 'INR', 'grand_total' => 40000, 'status' => 'sent']);

        $portalUser = $this->portalUser($customer);
        $this->asPortalUser($portalUser)->get("/portal/quotations/{$quotation->id}")->assertOk()->assertSee('RetailPOS rollout')->assertDontSee('Never show this');
        $this->asPortalUser($portalUser)->get("/portal/quotations/{$otherQuotation->id}")->assertNotFound();
    }

    public function test_portal_ticket_creation_and_reply_stay_customer_safe_and_notify_internal_team(): void
    {
        $manager = $this->manager();
        $customer = $this->customer($manager);
        $portalUser = $this->portalUser($customer);

        $this->asPortalUser($portalUser)->post('/portal/support', ['subject' => 'Printer is unavailable', 'category' => 'hardware', 'priority' => 'high', 'description' => 'The receipt printer is unavailable at opening.', 'contact_phone' => '+91 90000 11111'])
            ->assertRedirect();
        $ticket = CrmSupportTicket::firstOrFail();
        $this->assertSame('customer_portal', $ticket->source->value);
        $this->assertSame($customer->id, $ticket->customer_id);
        $this->assertDatabaseHas('crm_support_ticket_messages', ['ticket_id' => $ticket->id, 'visibility' => 'customer_safe', 'customer_portal_user_id' => $portalUser->id]);
        $this->assertTrue($manager->notifications()->where('data->event_key', 'crm.support_ticket_created')->exists());

        $ticket->messages()->create(['message' => 'Internal diagnostics only.', 'visibility' => 'internal', 'message_type' => 'note', 'created_by' => $manager->id]);
        $this->asPortalUser($portalUser)->get("/portal/support/{$ticket->id}")->assertOk()->assertDontSee('Internal diagnostics only.');
        $this->asPortalUser($portalUser)->post("/portal/support/{$ticket->id}/replies", ['message' => 'The issue still happens after restart.'])->assertRedirect();
        $this->assertDatabaseHas('crm_support_ticket_messages', ['ticket_id' => $ticket->id, 'message' => 'The issue still happens after restart.', 'visibility' => 'customer_safe', 'customer_portal_user_id' => $portalUser->id]);
    }

    public function test_portal_only_shows_own_proforma_and_customer_safe_onboarding_updates(): void
    {
        $manager = $this->manager();
        $customer = $this->customer($manager);
        $portalUser = $this->portalUser($customer);
        $proforma = CrmProformaInvoice::create(['company_id' => $manager->company_id, 'customer_id' => $customer->id, 'proforma_number' => 'PI-001', 'title' => 'Implementation invoice', 'currency' => 'INR', 'grand_total' => 20000, 'paid_amount' => 5000, 'balance_amount' => 15000, 'invoice_date' => now()->toDateString(), 'status' => 'partially_paid', 'internal_remarks' => 'Never visible internally.']);
        $onboarding = CrmCustomerOnboarding::create(['company_id' => $manager->company_id, 'customer_id' => $customer->id, 'onboarding_number' => 'ONB-001', 'title' => 'Retail implementation', 'status' => 'in_progress', 'priority' => 'normal', 'progress_percent' => 50, 'internal_remarks' => 'Private project risk.']);
        CrmOnboardingNote::create(['onboarding_id' => $onboarding->id, 'note' => 'Private internal note.', 'visibility' => 'internal']);
        CrmOnboardingNote::create(['onboarding_id' => $onboarding->id, 'note' => 'Your store data review is complete.', 'visibility' => 'customer_safe']);

        $this->asPortalUser($portalUser)->get("/portal/proformas/{$proforma->id}")->assertOk()->assertSee('INR 15,000.00')->assertDontSee('Never visible internally.');
        $this->asPortalUser($portalUser)->get("/portal/onboarding/{$onboarding->id}")->assertOk()->assertSee('Your store data review is complete.')->assertDontSee('Private internal note.')->assertDontSee('Private project risk.');
    }

    public function test_service_request_creates_linked_customer_portal_lead_activity_and_notification(): void
    {
        $manager = $this->manager();
        $customer = $this->customer($manager);
        $this->newStatus($manager);
        $portalUser = $this->portalUser($customer);

        $this->asPortalUser($portalUser)->post('/portal/services/request', ['service_category' => 'ERP', 'requirement_summary' => 'We need purchase approvals integrated with our retail operations.', 'preferred_contact_method' => 'whatsapp', 'urgency' => 'high', 'budget_range' => 'INR 2-4 lakh'])
            ->assertRedirect('/portal/services');

        $lead = CrmLead::firstOrFail();
        $this->assertSame($customer->id, $lead->customer_id);
        $this->assertSame('customer-portal', $lead->source->slug);
        $this->assertSame('ERP', $lead->metadata['service_category']);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'Customer requested new service from portal: ERP.']);
        $this->assertTrue($manager->notifications()->where('data->event_key', 'crm.lead.created')->exists());
        $this->actingAs($manager)->get("/crm/customers/{$customer->id}")->assertOk()->assertSee('Service Requests')->assertSee('ERP');
    }

    public function test_portal_session_cannot_access_crm_and_unsafe_links_are_rejected(): void
    {
        $portalUser = $this->portalUser($this->customer($this->manager()));
        $this->asPortalUser($portalUser)->get('/crm')->assertRedirect('/login');
        $this->asPortalUser($portalUser)->post('/portal/support', ['subject' => 'Unsafe', 'category' => 'general', 'priority' => 'normal', 'description' => 'Test validation.', 'external_url' => 'javascript:alert(1)'])->assertSessionHasErrors('external_url');
    }

    public function test_service_requests_are_rate_limited_per_portal_user(): void
    {
        $manager = $this->manager();
        $this->newStatus($manager);
        $portalUser = $this->portalUser($this->customer($manager));
        $payload = ['service_category' => 'CRM', 'requirement_summary' => 'We need a customer follow-up workflow.', 'preferred_contact_method' => 'email', 'urgency' => 'normal'];

        foreach (range(1, 3) as $attempt) $this->asPortalUser($portalUser)->post('/portal/services/request', $payload)->assertRedirect('/portal/services');
        $this->asPortalUser($portalUser)->post('/portal/services/request', $payload)->assertStatus(429);
    }

    private function asPortalUser(CrmCustomerPortalUser $portalUser): static
    {
        return $this->withSession(['customer_portal_user_id' => $portalUser->id]);
    }

    private function manager(): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }

    private function customer(User $manager, string $name = 'Northstar Retail'): CrmCustomer
    {
        return CrmCustomer::create(['company_id' => $manager->company_id, 'customer_code' => 'RPC-'.str_pad((string) (CrmCustomer::query()->count() + 1), 6, '0', STR_PAD_LEFT), 'company_name' => $name, 'display_name' => 'Asha Mehta', 'email' => 'asha@example.test', 'phone' => '+91 90000 11111', 'status' => CrmCustomerStatus::Active, 'created_by' => $manager->id]);
    }

    private function portalUser(CrmCustomer $customer, string $email = 'asha@example.test', string $status = 'active'): CrmCustomerPortalUser
    {
        return CrmCustomerPortalUser::create(['customer_id' => $customer->id, 'name' => 'Asha Mehta', 'email' => $email, 'phone' => '+91 90000 11111', 'status' => $status])->load('customer');
    }

    private function token(CrmCustomerPortalUser $portalUser, string $raw, $expiresAt = null): void
    {
        CrmCustomerPortalToken::create(['customer_portal_user_id' => $portalUser->id, 'token_hash' => hash('sha256', $raw), 'purpose' => 'login', 'expires_at' => $expiresAt ?? now()->addHour()]);
    }

    private function newStatus(User $manager): CrmLeadStatus
    {
        return CrmLeadStatus::firstOrCreate(['company_id' => $manager->company_id, 'slug' => 'new'], ['name' => 'New', 'stage_type' => LeadStageType::New, 'tone' => 'neutral', 'probability' => 10, 'is_active' => true, 'sort_order' => 1]);
    }

    private function lead(User $manager, CrmCustomer $customer, string $title = 'Retail expansion'): CrmLead
    {
        $status = $this->newStatus($manager);
        $lead = CrmLead::create(['company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'customer_id' => $customer->id, 'status_id' => $status->id, 'title' => $title, 'business_name' => $customer->company_name, 'contact_name' => $customer->display_name, 'email' => $customer->email, 'phone' => $customer->phone, 'priority' => 'medium']);
        $customer->update(['lead_id' => $lead->id]);

        return $lead;
    }
}
