<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class SalesReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_completed_sales_are_scoped_and_voided_sales_are_excluded(): void
    {
        $company = Company::factory()->create();
        $one = Branch::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $two = Branch::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $admin = User::factory()->create(['company_id' => $company->id, 'branch_id' => $one->id, 'role' => UserRole::Administrator, 'is_active' => true]);
        $this->reportSale($company, $one, $admin, 'S-1', '19.99');
        $this->reportSale($company, $one, $admin, 'S-VOID', '99.99', 'voided');
        $this->reportSale($company, $two, $admin, 'S-2', '29.99');

        $this->actingAs($admin)->get('/reports/sales?outlet_id='.$one->id)->assertOk()->assertSee('S-1')->assertDontSee('S-VOID')->assertDontSee('S-2');
        $this->actingAs($admin)->get('/reports/sales?outlet_id=all')->assertOk()->assertSee('S-1')->assertSee('S-2')->assertDontSee('S-VOID');
    }

    public function test_sales_reports_keep_tenants_isolated_and_administrators_can_consolidate_their_own_outlets(): void
    {
        $company = Company::factory()->create();
        $first = $this->reportBranch($company, 'First Outlet');
        $second = $this->reportBranch($company, 'Second Outlet');
        $administrator = $this->reportUser($company, $first);
        $otherCompany = Company::factory()->create();
        $otherOutlet = $this->reportBranch($otherCompany, 'Other Tenant Outlet');
        $otherAdministrator = $this->reportUser($otherCompany, $otherOutlet);

        $this->reportSale($company, $first, $administrator, 'TENANT-A-ONE', '10.01');
        $this->reportSale($company, $second, $administrator, 'TENANT-A-TWO', '20.02');
        $this->reportSale($otherCompany, $otherOutlet, $otherAdministrator, 'TENANT-B', '900.00');

        $report = $this->reportFor($administrator, 'sales', ['outlet_id' => 'all']);

        $this->assertSame(3003, $report['detail']['net_sales']);
        $this->assertSame(2, $report['detail']['count']);
        $this->actingAs($administrator)->get('/reports/sales?outlet_id=all')->assertOk()->assertSee('TENANT-A-ONE')->assertSee('TENANT-A-TWO')->assertDontSee('TENANT-B');
    }

    public function test_outlet_managers_cannot_expand_scope_and_cashier_staff_cannot_open_reports(): void
    {
        $company = Company::factory()->create();
        $assigned = $this->reportBranch($company, 'Assigned Outlet');
        $unassigned = $this->reportBranch($company, 'Unassigned Outlet');
        $manager = $this->reportUser($company, $assigned, UserRole::Manager);
        $cashier = $this->reportUser($company, $assigned, UserRole::Staff);

        $this->actingAs($manager)->get('/reports/sales?outlet_id=all')->assertSessionHasErrors('outlet_id');
        $this->actingAs($manager)->get('/reports/sales?outlet_id='.$unassigned->id)->assertSessionHasErrors('outlet_id');
        $this->actingAs($cashier)->get('/reports/sales?outlet_id='.$assigned->id)->assertForbidden();
    }

    public function test_sales_date_boundaries_are_inclusive_in_the_company_timezone(): void
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata']);
        $outlet = $this->reportBranch($company, 'Boundary Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $start = now('Asia/Kolkata')->startOfDay();
        $end = $start->copy()->addDay()->endOfDay();

        $this->reportSale($company, $outlet, $administrator, 'START-DATE', '10.00', 'completed', $start);
        $this->reportSale($company, $outlet, $administrator, 'END-DATE', '20.00', 'completed', $end);
        $this->reportSale($company, $outlet, $administrator, 'OUTSIDE-DATE', '30.00', 'completed', $end->copy()->addSecond());

        $report = $this->reportFor($administrator, 'sales', [
            'outlet_id' => $outlet->id,
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
        ]);

        $this->assertSame(3000, $report['detail']['net_sales']);
        $this->assertSame(['END-DATE', 'START-DATE'], array_column($report['detail']['rows'], 'reference'));
    }

    public function test_sales_csv_uses_the_same_authorized_rows_as_the_detail_screen(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'CSV Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $this->reportSale($company, $outlet, $administrator, 'CSV-SALE', '49.95');
        $this->reportSale($company, $outlet, $administrator, 'CSV-VOID', '10.00', 'voided');

        $detail = $this->actingAs($administrator)->get('/reports/sales?outlet_id='.$outlet->id);
        $csv = $this->actingAs($administrator)->get('/reports/sales/export?outlet_id='.$outlet->id);

        $detail->assertOk()->assertSee('CSV-SALE')->assertDontSee('CSV-VOID');
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('CSV-SALE', $csv->streamedContent());
        $this->assertStringNotContainsString('CSV-VOID', $csv->streamedContent());
        $this->assertStringContainsString('49.95', $csv->streamedContent());
    }

    public function test_linked_crm_invoices_do_not_inflate_pos_sales_and_totals_preserve_minor_units(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Precision Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $this->reportSale($company, $outlet, $administrator, 'POS-10-01', '10.01');
        $this->reportSale($company, $outlet, $administrator, 'POS-20-02', '20.02');
        $this->reportInvoice($company, $outlet, 'CRM-RELATED-ONLY', '30.03', '30.03');

        $report = $this->reportFor($administrator, 'sales', ['outlet_id' => $outlet->id]);

        $this->assertSame(3003, $report['detail']['net_sales']);
        $this->assertSame(2, $report['detail']['count']);
        $this->assertEqualsCanonicalizing(['POS-20-02', 'POS-10-01'], array_column($report['detail']['rows'], 'reference'));
    }
}
