<?php

namespace Tests\Feature;

use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmQuotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_a_quotation_with_calculated_totals_and_lead_history(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $lead = $this->lead($manager, $sales);

        $this->actingAs($manager)
            ->post("/crm/leads/{$lead->id}/quotations", $this->payload())
            ->assertRedirect();

        $quotation = CrmQuotation::query()->firstOrFail();

        $this->assertSame('RPQ-'.now()->format('Y').'-000001', $quotation->quotation_number);
        $this->assertSame('2500.00', $quotation->subtotal);
        $this->assertSame('100.00', $quotation->discount_total);
        $this->assertSame('432.00', $quotation->tax_total);
        $this->assertSame('2832.00', $quotation->grand_total);
        $this->assertCount(2, $quotation->items);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => "Quotation {$quotation->quotation_number} created."]);
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.quotation.created')->exists());

        $this->actingAs($manager)->get("/crm/leads/{$lead->id}")
            ->assertOk()
            ->assertSee('Related Quotations')
            ->assertSee($quotation->quotation_number);
    }

    public function test_staff_cannot_create_a_quotation(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);
        $lead = $this->lead($manager, $manager);

        $this->actingAs($staff)->post("/crm/leads/{$lead->id}/quotations", $this->payload())->assertForbidden();
        $this->assertDatabaseCount('crm_quotations', 0);
    }

    public function test_quotation_list_filter_search_and_public_link_work_without_login(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, $manager);
        $quotation = $this->createQuotation($manager, $lead, ['customer_company' => 'Northstar Retail']);

        $this->actingAs($manager)->get('/crm/quotations?search=Northstar&status=draft')
            ->assertOk()
            ->assertSee($quotation->quotation_number);

        $this->actingAs($manager)->post("/crm/quotations/{$quotation->id}/public-link")->assertRedirect();
        $quotation->refresh();

        $this->assertNotNull($quotation->public_token);
        $this->get('/q/'.$quotation->public_token)->assertOk()->assertSee($quotation->quotation_number)->assertSee('Northstar Retail');
        $this->get('/q/not-a-valid-public-token')->assertNotFound();
    }

    public function test_sent_accepted_and_rejected_quotations_update_the_lead_workflow_and_notifications(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $statuses = $this->statuses($manager);
        $lead = $this->lead($manager, $sales, $statuses['new']->id);
        $quotation = $this->createQuotation($manager, $lead);

        $this->actingAs($manager)->post("/crm/quotations/{$quotation->id}/send")->assertRedirect();
        $this->assertSame(QuotationStatus::Sent, $quotation->refresh()->status);
        $this->assertSame($statuses['proposal']->id, $lead->refresh()->status_id);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'Quotation sent.']);
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.quotation.sent')->exists());

        $this->actingAs($manager)->post("/crm/quotations/{$quotation->id}/accept")->assertRedirect();
        $this->assertSame(QuotationStatus::Accepted, $quotation->refresh()->status);
        $this->assertSame($statuses['won']->id, $lead->refresh()->status_id);
        $this->assertNotNull($lead->won_at);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'Quotation accepted.']);
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.quotation.accepted')->exists());

        $rejected = $this->createQuotation($manager, $lead);
        $this->actingAs($manager)->post("/crm/quotations/{$rejected->id}/send")->assertRedirect();
        $this->actingAs($manager)->post("/crm/quotations/{$rejected->id}/reject")->assertRedirect();
        $this->assertSame(QuotationStatus::Rejected, $rejected->refresh()->status);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'Quotation rejected.']);
    }

    public function test_quotation_numbers_increment_and_crm_dashboard_metrics_update(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, $manager);
        $first = $this->createQuotation($manager, $lead);
        $second = $this->createQuotation($manager, $lead);

        $this->assertSame('RPQ-'.now()->format('Y').'-000002', $second->quotation_number);
        $this->actingAs($manager)->get('/crm')
            ->assertOk()
            ->assertSee('Draft Quotations')
            ->assertSee('Latest Quotations')
            ->assertSee($first->quotation_number)
            ->assertSee($second->quotation_number);
    }

    /** @param array<string, mixed> $overrides */
    private function createQuotation(User $user, CrmLead $lead, array $overrides = []): CrmQuotation
    {
        $this->actingAs($user)->post("/crm/leads/{$lead->id}/quotations", $this->payload($overrides))->assertRedirect();

        return CrmQuotation::query()->latest('id')->with('items')->firstOrFail();
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'RetailPOS Growth Proposal',
            'customer_name' => 'Asha Mehta',
            'customer_company' => 'Demo Retail Group',
            'customer_email' => 'asha@example.test',
            'customer_phone' => '+91 90000 11111',
            'billing_address' => 'Sector 18, Noida',
            'currency' => 'INR',
            'valid_until' => now()->addDays(14)->toDateString(),
            'notes' => 'Implementation plan included.',
            'terms_conditions' => 'Taxes are calculated per line item.',
            'internal_remarks' => 'Follow up after proposal review.',
            'items' => [
                ['name' => 'Command Center setup', 'description' => 'CRM and dashboard configuration', 'quantity' => 1, 'unit_price' => 2000, 'discount_amount' => 100, 'tax_rate' => 18],
                ['name' => 'Team onboarding', 'description' => 'Remote enablement session', 'quantity' => 1, 'unit_price' => 500, 'discount_amount' => 0, 'tax_rate' => 18],
            ],
        ], $overrides);
    }

    private function lead(User $user, User $assignedUser, ?int $statusId = null): CrmLead
    {
        $statuses = $this->statuses($user);
        $source = CrmLeadSource::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'website-contact'], ['name' => 'Website Contact', 'is_active' => true]);

        return CrmLead::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'source_id' => $source->id,
            'status_id' => $statusId ?? $statuses['new']->id,
            'assigned_user_id' => $assignedUser->id,
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

    /** @return array<string, CrmLeadStatus> */
    private function statuses(User $user): array
    {
        return [
            'new' => CrmLeadStatus::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'new'], ['name' => 'New', 'stage_type' => LeadStageType::New, 'is_active' => true, 'sort_order' => 1]),
            'proposal' => CrmLeadStatus::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'proposal-sent'], ['name' => 'Proposal Sent', 'stage_type' => LeadStageType::Proposal, 'is_active' => true, 'sort_order' => 2]),
            'won' => CrmLeadStatus::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'won'], ['name' => 'Won', 'stage_type' => LeadStageType::Won, 'is_won' => true, 'is_active' => true, 'sort_order' => 3]),
        ];
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
