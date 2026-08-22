<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Services\Reports\ExecutiveReportingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class ExecutiveReportingDashboardTest extends TestCase
{
    use BuildsReportingData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-22 12:00:00', 'Asia/Kolkata'));
    }

    public function test_owner_command_center_reconciles_authoritative_sources_and_previous_period(): void
    {
        $fixture = $this->fixture();
        $dashboard = app(ExecutiveReportingService::class)->dashboard($fixture['administrator'], [
            'outlet_id' => 'all',
            'date_from' => today()->toDateString(),
            'date_to' => today()->toDateString(),
        ]);
        $kpis = collect($dashboard['kpis'])->keyBy('key');

        $this->assertSame(9000, $kpis['net_sales']['value']);
        $this->assertSame(3000, $kpis['gross_profit']['value']);
        $this->assertSame('33.3333', $kpis['gross_margin']['value']);
        $this->assertSame(4000, $kpis['purchases']['value']);
        $this->assertSame(6800, $kpis['receivables']['value']);
        $this->assertSame(4000, $kpis['payables']['value']);
        $this->assertSame(1400, $kpis['gst_position']['value']);
        $this->assertSame(30000, $kpis['inventory_value']['value']);
        $this->assertSame('80.0', $kpis['net_sales']['change']);
        $this->assertSame('percent', $kpis['net_sales']['change_unit']);
        $this->assertSame('-6.7', $kpis['gross_margin']['change']);
        $this->assertSame('points', $kpis['gross_margin']['change_unit']);
        $this->assertNull($kpis['inventory_value']['change']);
        $this->assertSame(1800, $dashboard['gst']['output']);
        $this->assertSame(400, $dashboard['gst']['input']);
        $this->assertSame(9000, collect($dashboard['charts']['operations']['points'])->sum('sales'));
        $this->assertSame(4000, collect($dashboard['charts']['operations']['points'])->sum('purchases'));
        $this->assertSame('Executive product', $dashboard['products']['top'][0]['dimension']);
        $this->assertSame('Executive Outlet', $dashboard['outlets'][0]['outlet']);
        $this->assertSame(3000, $dashboard['outlets'][0]['gross_profit']);
        $this->assertSame('Reporting customer', $dashboard['financial']['customers'][0]['name']);
        $this->assertSame('Executive Supplier', $dashboard['financial']['suppliers'][0]['name']);
    }

    public function test_dashboard_page_and_export_render_the_shared_authorized_read_model(): void
    {
        $fixture = $this->fixture();
        $query = '?outlet_id=all&date_from='.today()->toDateString().'&date_to='.today()->toDateString();

        $page = $this->actingAs($fixture['administrator'])->get('/reports'.$query);
        $page->assertOk()
            ->assertSee('Owner Command Center')
            ->assertSee('Revenue vs gross profit')
            ->assertSee('Sales vs purchases')
            ->assertSee('Executive product')
            ->assertSee('Executive Supplier')
            ->assertDontSee('OTHER-TENANT-SALE');

        $export = $this->actingAs($fixture['administrator'])->get('/reports/executive/export'.$query);
        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Owner Command Center', $csv);
        $this->assertStringContainsString('"Net Sales",90.00', $csv);
        $this->assertStringContainsString('"Gross Profit",30.00', $csv);
        $this->assertStringContainsString('"GST position",14.00', $csv);
    }

    public function test_outlet_manager_cannot_escape_authorized_scope_and_sales_user_does_not_receive_profitability(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['manager'])->get('/reports?outlet_id=all')->assertSessionHasErrors('outlet_id');
        $this->actingAs($fixture['manager'])->get('/reports?outlet_id='.$fixture['otherOutlet']->id)->assertSessionHasErrors('outlet_id');

        $salesPage = $this->actingAs($fixture['sales'])->get('/reports?outlet_id='.$fixture['outlet']->id.'&compare=0');
        $salesPage->assertOk()->assertSee('Gross Profit')->assertSee('Unavailable')->assertDontSee('Executive product');
        $this->actingAs($fixture['sales'])->get('/reports/executive/export')->assertForbidden();
    }

    public function test_comparison_and_ranked_lists_exclude_cancelled_and_cross_tenant_records(): void
    {
        $fixture = $this->fixture();
        $dashboard = app(ExecutiveReportingService::class)->dashboard($fixture['administrator'], [
            'outlet_id' => 'all',
            'date_from' => today()->toDateString(),
            'date_to' => today()->toDateString(),
        ]);

        $this->assertSame(1, count($dashboard['products']['top']));
        $this->assertSame(1, count($dashboard['financial']['customers']));
        $this->assertSame(1, count($dashboard['financial']['suppliers']));
        $this->assertNotContains('Other Tenant Outlet', array_column($dashboard['outlets'], 'outlet'));
        $this->assertSame(9000, collect($dashboard['charts']['profitability']['points'])->sum('net_sales'));
        $this->assertSame(3000, collect($dashboard['charts']['profitability']['points'])->sum('gross_profit'));
    }

    public function test_executive_export_quotes_formula_like_scope_labels(): void
    {
        $fixture = $this->fixture();
        $fixture['outlet']->update(['name' => '=HYPERLINK("unsafe")']);

        $response = $this->actingAs($fixture['administrator'])->get('/reports/executive/export?outlet_id='.$fixture['outlet']->id.'&compare=0');

        $response->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_profitability_chart_has_distinct_single_and_multi_period_presentations(): void
    {
        $single = Blade::render('<x-reports.line-chart :points="$points" />', ['points' => [
            ['label' => '22 Aug', 'net_sales' => 47000, 'gross_profit' => 14000],
        ]]);
        $multi = Blade::render('<x-reports.line-chart :points="$points" />', ['points' => [
            ['label' => '21 Aug', 'net_sales' => 30000, 'gross_profit' => 9000],
            ['label' => '22 Aug', 'net_sales' => 47000, 'gross_profit' => 14000],
        ]]);

        $this->assertStringContainsString('Single-period result', $single);
        $this->assertStringContainsString('INR 470.00', $single);
        $this->assertStringContainsString('INR 140.00', $single);
        $this->assertStringNotContainsString('<polyline', $single);
        $this->assertStringContainsString('<polyline', $multi);
        $this->assertStringNotContainsString('Single-period result', $multi);
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $company = Company::factory()->create(['name' => 'Executive Retail', 'timezone' => 'Asia/Kolkata']);
        $outlet = $this->reportBranch($company, 'Executive Outlet');
        $otherOutlet = $this->reportBranch($company, 'Unassigned Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $manager = $this->reportUser($company, $outlet, UserRole::Manager);
        $sales = $this->reportUser($company, $outlet, UserRole::Sales);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Executive Warehouse');
        $supplier = $this->reportSupplier($company, 'Executive Supplier');
        $product = $this->reportProduct($company, $outlet, 'Executive product', '60.00');
        $this->reportStockLevel($company, $outlet, $warehouse, $product, '5.000');

        $sale = $this->reportSale($company, $outlet, $administrator, 'EXEC-SALE', '90.00', 'completed', now());
        $sale->update(['subtotal' => '100.00', 'discount_amount' => '10.00', 'total_amount' => '90.00', 'paid_amount' => '90.00']);
        $item = $this->reportSaleItem($sale, $product, null, '1.000', '90.00');
        $item->update(['gross_amount' => '100.00', 'discount_amount' => '10.00', 'taxable_amount' => '90.00', 'gross_sales_snapshot' => '100.00', 'net_sales_snapshot' => '90.00', 'unit_cost_snapshot' => '60.00', 'total_cost_snapshot' => '60.00', 'gross_profit_before_discount' => '40.00', 'gross_profit_snapshot' => '30.00', 'cost_snapshot_status' => 'captured', 'cost_snapshot_method' => 'standard_cost']);
        $previousSale = $this->reportSale($company, $outlet, $administrator, 'EXEC-PREVIOUS', '50.00', 'completed', now()->subDay());
        $previousItem = $this->reportSaleItem($previousSale, $product, null, '1.000', '50.00');
        $previousItem->update(['gross_amount' => '50.00', 'taxable_amount' => '50.00', 'gross_sales_snapshot' => '50.00', 'net_sales_snapshot' => '50.00', 'unit_cost_snapshot' => '30.00', 'total_cost_snapshot' => '30.00', 'gross_profit_before_discount' => '20.00', 'gross_profit_snapshot' => '20.00', 'cost_snapshot_status' => 'captured', 'cost_snapshot_method' => 'standard_cost']);

        $purchase = $this->reportPurchaseInvoice($company, $outlet, $warehouse, $supplier, $administrator, 'EXEC-PURCHASE', '40.00');
        $purchase->update(['input_cgst' => '2.00', 'input_sgst' => '2.00', 'outstanding_total' => '40.00']);
        $this->reportPurchaseInvoice($company, $outlet, $warehouse, $supplier, $administrator, 'EXEC-CANCELLED-PURCHASE', '999.00', 'cancelled');
        $invoice = $this->reportInvoice($company, $outlet, 'EXEC-RECEIVABLE', '118.00', '68.00', issueDate: today(), dueDate: today()->subDay());
        $invoice->update(['taxable_total' => '100.00', 'cgst_total' => '9.00', 'sgst_total' => '9.00', 'tax_total' => '18.00', 'place_of_supply_state_code' => 'KA']);
        $this->reportInvoice($company, $outlet, 'EXEC-CANCELLED-INVOICE', '500.00', '500.00', 'cancelled');

        $otherCompany = Company::factory()->create();
        $otherTenantOutlet = $this->reportBranch($otherCompany, 'Other Tenant Outlet');
        $otherAdmin = $this->reportUser($otherCompany, $otherTenantOutlet);
        $this->reportSale($otherCompany, $otherTenantOutlet, $otherAdmin, 'OTHER-TENANT-SALE', '999.00');

        return compact('company', 'outlet', 'otherOutlet', 'administrator', 'manager', 'sales');
    }
}
