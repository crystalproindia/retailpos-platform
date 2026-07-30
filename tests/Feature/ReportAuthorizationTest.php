<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class ReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_tenant_a_cannot_access_tenant_b_through_report_scope_parameters_or_drill_down(): void
    {
        $firstCompany = Company::factory()->create();
        $firstOutlet = $this->reportBranch($firstCompany, 'Tenant A Outlet');
        $firstAdministrator = $this->reportUser($firstCompany, $firstOutlet);
        $secondCompany = Company::factory()->create();
        $secondOutlet = $this->reportBranch($secondCompany, 'Tenant B Outlet');
        $secondAdministrator = $this->reportUser($secondCompany, $secondOutlet);

        $this->reportSale($firstCompany, $firstOutlet, $firstAdministrator, 'SALE-TENANT-A', '10.00');
        $this->reportSale($secondCompany, $secondOutlet, $secondAdministrator, 'SALE-TENANT-B', '20.00');

        $this->actingAs($firstAdministrator)->get('/reports/sales?outlet_id='.$secondOutlet->id)->assertSessionHasErrors('outlet_id');
        $this->actingAs($firstAdministrator)->get('/reports/sales/export?outlet_id='.$secondOutlet->id)->assertSessionHasErrors('outlet_id');
        $this->actingAs($firstAdministrator)->get('/reports/sales?outlet_id=all')->assertOk()->assertSee('SALE-TENANT-A')->assertDontSee('SALE-TENANT-B');
    }

    public function test_outlet_managers_cannot_select_unassigned_or_all_outlets_in_ui_drill_down_or_export(): void
    {
        $company = Company::factory()->create();
        $assigned = $this->reportBranch($company, 'Assigned Authorization Outlet');
        $unassigned = $this->reportBranch($company, 'Unassigned Authorization Outlet');
        $manager = $this->reportUser($company, $assigned, UserRole::Manager);

        $this->actingAs($manager)->get('/reports?outlet_id=all')->assertSessionHasErrors('outlet_id');
        $this->actingAs($manager)->get('/reports/sales?outlet_id='.$unassigned->id)->assertSessionHasErrors('outlet_id');
        $this->actingAs($manager)->get('/reports/sales/export?outlet_id='.$unassigned->id)->assertSessionHasErrors('outlet_id');
    }

    public function test_staff_cashiers_cannot_view_drill_downs_or_exports_and_administrators_receive_correct_consolidated_totals(): void
    {
        $company = Company::factory()->create();
        $first = $this->reportBranch($company, 'First Consolidated Outlet');
        $second = $this->reportBranch($company, 'Second Consolidated Outlet');
        $administrator = $this->reportUser($company, $first);
        $cashier = $this->reportUser($company, $first, UserRole::Staff);

        $this->reportSale($company, $first, $administrator, 'SALE-FIRST-CONSOLIDATED', '10.01');
        $this->reportSale($company, $second, $administrator, 'SALE-SECOND-CONSOLIDATED', '20.02');

        $report = $this->reportFor($administrator, 'sales', ['outlet_id' => 'all']);

        $this->assertSame(3003, $report['detail']['net_sales']);
        $this->actingAs($cashier)->get('/reports')->assertForbidden();
        $this->actingAs($cashier)->get('/reports/sales?outlet_id='.$first->id)->assertForbidden();
        $this->actingAs($cashier)->get('/reports/sales/export?outlet_id='.$first->id)->assertForbidden();
    }

    public function test_consecutive_company_report_requests_do_not_leak_scoped_values_between_tenants(): void
    {
        $firstCompany = Company::factory()->create();
        $firstOutlet = $this->reportBranch($firstCompany, 'First Cache Isolation Outlet');
        $firstAdministrator = $this->reportUser($firstCompany, $firstOutlet);
        $secondCompany = Company::factory()->create();
        $secondOutlet = $this->reportBranch($secondCompany, 'Second Cache Isolation Outlet');
        $secondAdministrator = $this->reportUser($secondCompany, $secondOutlet);

        $this->reportSale($firstCompany, $firstOutlet, $firstAdministrator, 'SALE-FIRST-CACHE', '11.11');
        $this->reportSale($secondCompany, $secondOutlet, $secondAdministrator, 'SALE-SECOND-CACHE', '22.22');

        $firstResponse = $this->actingAs($firstAdministrator)->get('/reports/sales?outlet_id=all');
        $secondResponse = $this->actingAs($secondAdministrator)->get('/reports/sales?outlet_id=all');

        $firstResponse->assertOk()->assertSee('SALE-FIRST-CACHE')->assertDontSee('SALE-SECOND-CACHE');
        $secondResponse->assertOk()->assertSee('SALE-SECOND-CACHE')->assertDontSee('SALE-FIRST-CACHE');
    }
}
