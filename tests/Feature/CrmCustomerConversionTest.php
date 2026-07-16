<?php

namespace Tests\Feature;

use App\Enums\Crm\CrmCustomerStatus;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmCustomerConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_convert_a_lead_into_a_crm_customer_with_history_and_notification(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $lead = $this->lead($manager, $sales);

        $this->actingAs($manager)
            ->get("/crm/leads/{$lead->id}/customer-conversion")
            ->assertOk()
            ->assertSee('Create a CRM customer from this lead')
            ->assertSee('Demo Retail Group');

        $this->actingAs($manager)
            ->post("/crm/leads/{$lead->id}/customer-conversion", $this->payload())
            ->assertRedirect();

        $customer = CrmCustomer::query()->with('primaryContact')->firstOrFail();

        $this->assertSame('RPC-'.now()->format('Y').'-000001', $customer->customer_code);
        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame(CrmCustomerStatus::Onboarding, $customer->status);
        $this->assertSame('Asha Mehta', $customer->primaryContact?->name);
        $this->assertTrue((bool) $customer->primaryContact?->is_primary);
        $this->assertNotNull($lead->refresh()->converted_at);
        $this->assertNotNull($lead->won_at);
        $this->assertSame($this->leadStatus($manager, 'won', LeadStageType::Won)->id, $lead->status_id);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => "Lead converted to customer {$customer->customer_code}."]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.customer.created', 'auditable_id' => $customer->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.lead.converted_customer', 'auditable_id' => $lead->id]);
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.customer.created')->exists());

        $this->actingAs($manager)
            ->get("/crm/customers/{$customer->id}")
            ->assertOk()
            ->assertSee($customer->customer_code)
            ->assertSee('Linked lead')
            ->assertSee($lead->title);
    }

    public function test_lead_can_only_be_converted_once(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, $manager);

        $this->actingAs($manager)->post("/crm/leads/{$lead->id}/customer-conversion", $this->payload())->assertRedirect();
        $this->actingAs($manager)
            ->post("/crm/leads/{$lead->id}/customer-conversion", $this->payload())
            ->assertSessionHasErrors('lead');

        $this->assertDatabaseCount('crm_customers', 1);
    }

    public function test_only_accepted_quotation_can_be_converted_and_linked_to_customer(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, $manager);
        $accepted = $this->quotation($manager, $lead, QuotationStatus::Accepted);

        $this->actingAs($manager)
            ->get("/crm/quotations/{$accepted->id}/customer-conversion")
            ->assertOk()
            ->assertSee($accepted->quotation_number);

        $this->actingAs($manager)
            ->post("/crm/quotations/{$accepted->id}/customer-conversion", $this->payload(['company_name' => 'Accepted Retail Group']))
            ->assertRedirect();

        $customer = CrmCustomer::query()->firstOrFail();
        $this->assertSame($accepted->id, $customer->quotation_id);
        $this->assertSame(QuotationStatus::Converted, $accepted->refresh()->status);
        $this->assertNotNull($accepted->converted_at);

        $this->actingAs($manager)
            ->get("/crm/quotations/{$accepted->id}")
            ->assertOk()
            ->assertSee('Converted CRM customer')
            ->assertSee($customer->customer_code);

        $draftLead = $this->lead($manager, $manager, ['title' => 'Draft-only lead']);
        $draft = $this->quotation($manager, $draftLead, QuotationStatus::Draft);
        $this->actingAs($manager)->get("/crm/quotations/{$draft->id}/customer-conversion")->assertSessionHasErrors('quotation');
    }

    public function test_staff_cannot_access_crm_customer_conversion_or_customers(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);
        $lead = $this->lead($manager, $manager);

        $this->actingAs($staff)->get('/crm/customers')->assertForbidden();
        $this->actingAs($staff)->get("/crm/leads/{$lead->id}/customer-conversion")->assertForbidden();
        $this->actingAs($staff)->post("/crm/leads/{$lead->id}/customer-conversion", $this->payload())->assertForbidden();
    }

    public function test_customer_list_search_filters_and_dashboard_are_company_scoped(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, $manager);
        $this->actingAs($manager)->post("/crm/leads/{$lead->id}/customer-conversion", $this->payload(['company_name' => 'Northstar Retail']))->assertRedirect();
        $customer = CrmCustomer::query()->firstOrFail();
        $customer->update(['status' => CrmCustomerStatus::Active]);

        $outside = $this->user(UserRole::Manager);
        $outsideLead = $this->lead($outside, $outside, ['title' => 'Outside Customer Lead']);
        $this->actingAs($outside)->post("/crm/leads/{$outsideLead->id}/customer-conversion", $this->payload(['company_name' => 'Outside Retail']))->assertRedirect();

        $this->actingAs($manager)
            ->get('/crm/customers?search=Northstar&status=active&business_type=Retail')
            ->assertOk()
            ->assertSee('Northstar Retail')
            ->assertDontSee('Outside Retail');

        $this->actingAs($manager)
            ->get('/crm')
            ->assertOk()
            ->assertSee('Total Customers')
            ->assertSee('Active Customers')
            ->assertSee('Latest CRM Customers')
            ->assertSee($customer->customer_code);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Demo Retail Group',
            'display_name' => 'Asha Mehta',
            'contact_name' => 'Asha Mehta',
            'email' => 'asha@example.test',
            'phone' => '+91 90000 11111',
            'designation' => 'Founder',
            'business_type' => 'Retail',
            'country' => 'India',
            'state' => 'Uttar Pradesh',
            'city' => 'Noida',
            'billing_address' => 'Sector 18, Noida',
            'tax_number' => 'GSTIN-TEST',
            'number_of_stores' => 4,
            'status' => CrmCustomerStatus::Onboarding->value,
            'notes' => 'Converted after a qualified discovery call.',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function lead(User $user, User $assignedUser, array $overrides = []): CrmLead
    {
        $source = CrmLeadSource::firstOrCreate(
            ['company_id' => $user->company_id, 'slug' => 'website-contact'],
            ['name' => 'Website Contact', 'is_active' => true],
        );

        return CrmLead::create(array_merge([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'source_id' => $source->id,
            'status_id' => $this->leadStatus($user, 'new', LeadStageType::New)->id,
            'assigned_user_id' => $assignedUser->id,
            'created_by' => $user->id,
            'title' => 'Enterprise Retail Discovery',
            'business_name' => 'Demo Retail Group',
            'contact_name' => 'Asha Mehta',
            'email' => 'asha@example.test',
            'phone' => '+91 90000 11111',
            'city' => 'Noida',
            'country' => 'India',
            'business_type' => 'Retail',
            'currency' => 'INR',
            'priority' => LeadPriority::Medium,
        ], $overrides));
    }

    private function quotation(User $user, CrmLead $lead, QuotationStatus $status): CrmQuotation
    {
        return CrmQuotation::create([
            'lead_id' => $lead->id,
            'company_id' => $user->company_id,
            'quotation_number' => 'RPQ-'.now()->format('Y').'-'.str_pad((string) (CrmQuotation::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'title' => 'RetailPOS Proposal',
            'customer_name' => $lead->contact_name,
            'customer_company' => $lead->business_name,
            'customer_email' => $lead->email,
            'customer_phone' => $lead->phone,
            'billing_address' => 'Sector 18, Noida',
            'currency' => 'INR',
            'grand_total' => 2500,
            'status' => $status,
            'accepted_at' => $status === QuotationStatus::Accepted ? now() : null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function leadStatus(User $user, string $slug, LeadStageType $stage): CrmLeadStatus
    {
        return CrmLeadStatus::firstOrCreate(
            ['company_id' => $user->company_id, 'slug' => $slug],
            ['name' => str($slug)->headline()->toString(), 'stage_type' => $stage, 'is_active' => true, 'is_won' => $stage === LeadStageType::Won, 'sort_order' => 1],
        );
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
