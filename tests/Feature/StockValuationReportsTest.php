<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class StockValuationReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_current_stock_valuation_uses_on_hand_quantity_and_current_product_cost_in_minor_units(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Valuation Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Valuation Warehouse');
        $product = $this->reportProduct($company, $outlet, 'Valuation Product', '42.42');
        $this->reportStockLevel($company, $outlet, $warehouse, $product, '3.500');

        $report = $this->reportFor($administrator, 'inventory');

        $this->assertSame(14847, $report['detail']['value']);
        $this->assertSame(14847, $report['detail']['rows'][0]['value']);
        $this->assertSame('Current on-hand quantity multiplied by the product current cost price. This is current valuation, not historical FIFO or weighted-average valuation.', $report['detail']['method']);
    }

    public function test_negative_quantities_remain_negative_in_current_valuation_instead_of_becoming_positive_assets(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Negative Valuation Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Negative Valuation Warehouse');
        $product = $this->reportProduct($company, $outlet, 'Negative Valuation Product', '7.25');
        $this->reportStockLevel($company, $outlet, $warehouse, $product, '-1.500');

        $report = $this->reportFor($administrator, 'inventory');

        $this->assertSame(-1088, $report['detail']['value']);
        $this->assertSame(-1088, $report['detail']['rows'][0]['value']);
    }
}
