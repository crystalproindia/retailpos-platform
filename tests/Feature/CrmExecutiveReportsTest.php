<?php

namespace Tests\Feature;

use App\Enums\Crm\CrmCustomerStatus;
use App\Enums\Crm\LeadScoreCategory;
use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmCustomerPortalUser;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadScore;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmOnboardingTask;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmProformaPayment;
use App\Models\Crm\CrmSupportTicket;
use App\Models\User;
use App\Services\Crm\CrmExecutiveReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmExecutiveReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_manager_can_open_visualization_and_all_report_pages_while_staff_is_denied(): void
    {
        $manager = $this->manager();
        $this->seedReportData($manager);

        $this->actingAs($manager)->get('/crm/reports/visualization')->assertOk()->assertSee('Business Health Dashboard')->assertSee('Sales Health');
        foreach (['sales', 'payments', 'onboarding', 'support', 'customers'] as $report) $this->actingAs($manager)->get("/crm/reports/{$report}")->assertOk();
        $this->actingAs($manager)->get('/dashboard')->assertOk()->assertSee('Business Health Snapshot');

        $staff = $this->staff($manager);
        $this->actingAs($staff)->get('/crm/reports/visualization')->assertForbidden();
    }

    public function test_administrator_can_open_the_executive_dashboard_and_sales_only_sees_assigned_data(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $this->seedReportData($administrator);

        $this->actingAs($administrator)->get('/crm/reports/executive')->assertOk()->assertSee('Business Health Dashboard');

        $sales = $this->sales($administrator);
        $status = $this->leadStatus($administrator);
        $source = $this->source($administrator);
        $this->lead($sales, $status, $source, ['assigned_user_id' => $sales->id, 'title' => 'Assigned sales lead']);

        $health = app(CrmExecutiveReportService::class)->dashboard($sales);

        $this->assertSame(1, $health['areas']['sales']['metrics']['total_leads']);
    }

    public function test_health_score_detects_money_sales_onboarding_support_and_portal_growth_signals(): void
    {
        $manager = $this->manager();
        $data = $this->seedReportData($manager);
        $health = app(CrmExecutiveReportService::class)->dashboard($manager);

        $this->assertGreaterThan(0, $health['overall_score']);
        $this->assertSame(1, $health['areas']['sales']['metrics']['hot_leads']);
        $this->assertSame(1, $health['areas']['sales']['metrics']['stale_leads']);
        $this->assertSame(5000.0, $health['areas']['money']['metrics']['overdue_amount']);
        $this->assertSame(1, $health['areas']['onboarding']['metrics']['delayed_onboardings']);
        $this->assertSame(1, $health['areas']['onboarding']['metrics']['blocked_tasks']);
        $this->assertSame(1, $health['areas']['support']['metrics']['urgent_tickets']);
        $this->assertSame(1, $health['areas']['support']['metrics']['overdue_tickets']);
        $this->assertSame(1, $health['areas']['customers']['metrics']['portal_service_requests']);
        $this->assertNotEmpty($health['risks']);
        $this->assertSame($data['customer']->id, $data['portalLead']->customer_id);
    }

    public function test_date_filter_export_and_company_scope_are_enforced(): void
    {
        $manager = $this->manager();
        $data = $this->seedReportData($manager);
        $oldLead = $this->lead($manager, $data['status'], $data['source'], ['title' => 'Historical lead']);
        $oldLead->forceFill(['created_at' => now()->subMonths(4), 'updated_at' => now()->subMonths(4)])->saveQuietly();
        $other = $this->manager();
        $this->lead($other, $this->leadStatus($other), $this->source($other), ['title' => 'Other company lead']);

        $health = app(CrmExecutiveReportService::class)->dashboard($manager, ['range' => 'this_month']);
        $this->assertSame(2, $health['areas']['sales']['metrics']['total_leads']);
        $this->assertSame(1, $health['areas']['customers']['metrics']['portal_service_requests']);

        $this->actingAs($manager)->get('/crm/reports/sales/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($manager)->get('/crm/reports/visualization?range=custom&date_from='.now()->startOfMonth()->toDateString().'&date_to='.now()->endOfMonth()->toDateString())->assertOk()->assertSee('Business Health Dashboard');
    }

    /** @return array{customer: CrmCustomer, portalLead: CrmLead} */
    private function seedReportData(User $manager): array
    {
        $status = $this->leadStatus($manager);
        $source = $this->source($manager);
        $customer = CrmCustomer::create(['company_id' => $manager->company_id, 'customer_code' => 'RPC-001', 'company_name' => 'Northstar Retail', 'display_name' => 'Asha Mehta', 'status' => CrmCustomerStatus::Active, 'created_by' => $manager->id]);
        $lead = $this->lead($manager, $status, $source, ['customer_id' => $customer->id, 'title' => 'Hot retail lead', 'expected_value' => 100000, 'next_follow_up_at' => now()->subDay()]);
        $lead->forceFill(['updated_at' => now()->subDays(21)])->saveQuietly();
        CrmLeadScore::create(['company_id' => $manager->company_id, 'lead_id' => $lead->id, 'score' => 92, 'category' => LeadScoreCategory::Hot, 'confidence' => 'high', 'priority' => 'high', 'analyzed_at' => now()]);
        $portalSource = CrmLeadSource::create(['company_id' => $manager->company_id, 'name' => 'Customer Portal', 'slug' => 'customer-portal', 'is_active' => true, 'sort_order' => 2]);
        $portalLead = $this->lead($manager, $status, $portalSource, ['customer_id' => $customer->id, 'title' => 'ERP service request', 'priority' => 'high', 'expected_value' => 80000]);
        CrmCustomerPortalUser::create(['customer_id' => $customer->id, 'name' => 'Asha Mehta', 'email' => 'asha@example.test', 'status' => 'active']);
        $proforma = CrmProformaInvoice::create(['company_id' => $manager->company_id, 'customer_id' => $customer->id, 'lead_id' => $lead->id, 'proforma_number' => 'PI-001', 'title' => 'Retail implementation', 'currency' => 'INR', 'grand_total' => 10000, 'paid_amount' => 5000, 'balance_amount' => 5000, 'invoice_date' => now()->toDateString(), 'due_date' => now()->subDay()->toDateString(), 'status' => 'overdue']);
        CrmProformaPayment::create(['proforma_invoice_id' => $proforma->id, 'amount' => 5000, 'payment_date' => now()->toDateString(), 'payment_mode' => 'upi']);
        $onboarding = CrmCustomerOnboarding::create(['company_id' => $manager->company_id, 'customer_id' => $customer->id, 'lead_id' => $lead->id, 'onboarding_number' => 'ONB-001', 'title' => 'Retail rollout', 'status' => 'in_progress', 'priority' => 'normal', 'target_go_live_date' => now()->subDay()->toDateString(), 'created_by' => $manager->id]);
        CrmOnboardingTask::create(['onboarding_id' => $onboarding->id, 'task_key' => 'data-import', 'title' => 'Import data', 'category' => 'data_collection', 'status' => 'blocked', 'sort_order' => 1]);
        CrmSupportTicket::create(['company_id' => $manager->company_id, 'customer_id' => $customer->id, 'lead_id' => $lead->id, 'ticket_number' => 'TKT-'.now()->format('Y').'-000001', 'subject' => 'Urgent terminal issue', 'description' => 'A terminal is blocked.', 'category' => 'hardware', 'priority' => 'urgent', 'status' => 'open', 'source' => 'customer_portal', 'due_at' => now()->subHour(), 'first_response_due_at' => now()->subHour()]);

        return compact('customer', 'portalLead', 'status', 'source');
    }

    private function manager(): User
    {
        return $this->user(UserRole::Manager);
    }

    private function user(UserRole $role): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }

    private function staff(User $manager): User { return User::factory()->for($manager->company)->create(['branch_id' => $manager->branch_id, 'role' => UserRole::Staff]); }
    private function sales(User $manager): User { return User::factory()->for($manager->company)->create(['branch_id' => $manager->branch_id, 'role' => UserRole::Sales]); }
    private function leadStatus(User $manager): CrmLeadStatus { return CrmLeadStatus::firstOrCreate(['company_id' => $manager->company_id, 'slug' => 'new'], ['name' => 'New', 'stage_type' => LeadStageType::New, 'is_active' => true, 'sort_order' => 1]); }
    private function source(User $manager): CrmLeadSource { return CrmLeadSource::firstOrCreate(['company_id' => $manager->company_id, 'slug' => 'website-contact'], ['name' => 'Website Contact', 'is_active' => true, 'sort_order' => 1]); }
    /** @param array<string, mixed> $overrides */ private function lead(User $manager, CrmLeadStatus $status, CrmLeadSource $source, array $overrides = []): CrmLead { return CrmLead::create(array_merge(['company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'status_id' => $status->id, 'source_id' => $source->id, 'assigned_user_id' => $manager->id, 'title' => 'Retail lead', 'priority' => 'medium'], $overrides)); }
}
