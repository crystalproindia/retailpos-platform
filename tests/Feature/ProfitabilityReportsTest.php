<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class ProfitabilityReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_profitability_report_excludes_historical_items_without_reliable_cost_snapshots(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Profitability Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $sale = $this->reportSale($company, $outlet, $administrator, 'SALE-PROFITABILITY', '100.00');
        $product = $this->reportProduct($company, $outlet, 'Historical product', '40.00');
        $this->reportSaleItem($sale, $product);

        $report = $this->reportFor($administrator, 'profitability');

        $this->assertSame(0, $report['detail']['net_sales']);
        $this->assertSame(0, $report['detail']['cost_of_goods_sold']);
        $this->assertSame(0, $report['detail']['gross_profit']);
        $this->assertSame(1, $report['detail']['unavailable_cost_item_count']);
        $this->assertStringContainsString('historical item', $report['detail']['notice']);
    }
}
