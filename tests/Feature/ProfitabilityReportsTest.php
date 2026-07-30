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

    public function test_profitability_report_stays_explicitly_unavailable_without_reliable_invoice_cost_snapshots(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Profitability Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $this->reportSale($company, $outlet, $administrator, 'SALE-PROFITABILITY', '100.00');

        $report = $this->reportFor($administrator, 'profitability');

        $this->assertSame(10000, $report['detail']['net_sales']);
        $this->assertNull($report['detail']['cost_of_goods_sold']);
        $this->assertNull($report['detail']['gross_profit']);
        $this->assertSame('Gross profit is unavailable until a reliable invoice-level cost snapshot exists.', $report['detail']['notice']);
    }
}
