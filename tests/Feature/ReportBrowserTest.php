<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class ReportBrowserTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_authorized_report_screens_render_responsive_detail_containers_and_export_controls(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Browser Report Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $this->reportSale($company, $outlet, $administrator, 'BROWSER-SALE', '10.00');

        $this->actingAs($administrator)->get('/reports?outlet_id='.$outlet->id)
            ->assertOk()
            ->assertSee('Stock Movements');

        foreach (['sales', 'purchases', 'inventory', 'movements', 'profitability', 'gst', 'payments', 'outstanding', 'returns', 'outlets', 'cashiers'] as $report) {
            $this->actingAs($administrator)->get('/reports/'.$report.'?outlet_id='.$outlet->id)
                ->assertOk()
                ->assertSee('Export CSV')
                ->assertSee('More filters')
                ->assertSee('lg:hidden');
        }
    }

    public function test_empty_report_detail_explains_that_no_records_match_the_selected_scope(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Empty Browser Report Outlet');
        $administrator = $this->reportUser($company, $outlet);

        $this->actingAs($administrator)->get('/reports/sales?outlet_id='.$outlet->id)
            ->assertOk()
            ->assertSee('No detailed rows to show')
            ->assertSee('No records match the selected date range and filters.');
    }

    public function test_manager_filter_errors_are_visible_and_staff_cannot_open_any_report_screen(): void
    {
        $company = Company::factory()->create();
        $assigned = $this->reportBranch($company, 'Browser Assigned Outlet');
        $other = $this->reportBranch($company, 'Browser Other Outlet');
        $manager = $this->reportUser($company, $assigned, UserRole::Manager);
        $staff = $this->reportUser($company, $assigned, UserRole::Staff);

        $this->actingAs($manager)->get('/reports/sales?outlet_id='.$other->id)->assertSessionHasErrors('outlet_id');
        $this->actingAs($staff)->get('/reports/sales?outlet_id='.$assigned->id)->assertForbidden();
    }
}
