<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class StockMovementReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_stock_movements_are_tenant_and_outlet_scoped_with_exact_quantity_totals(): void
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata']);
        $outlet = $this->reportBranch($company, 'Movement Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Movement Warehouse');
        $product = $this->reportProduct($company, $outlet, 'Movement Product', '11.11');
        $start = now('Asia/Kolkata')->startOfDay();

        $this->reportStockMovement($company, $outlet, $warehouse, $product, $administrator, 'purchase', 'in', '2.500', '0.000', '2.500', $start);
        $this->reportStockMovement($company, $outlet, $warehouse, $product, $administrator, 'sale', 'out', '0.750', '2.500', '1.750', $start->copy()->endOfDay());

        $otherCompany = Company::factory()->create();
        $otherOutlet = $this->reportBranch($otherCompany, 'Other Tenant Movement Outlet');
        $otherAdministrator = $this->reportUser($otherCompany, $otherOutlet);
        $otherWarehouse = $this->reportWarehouse($otherCompany, $otherOutlet, 'Other Tenant Movement Warehouse');
        $otherProduct = $this->reportProduct($otherCompany, $otherOutlet, 'Other Tenant Movement Product', '11.11');
        $this->reportStockMovement($otherCompany, $otherOutlet, $otherWarehouse, $otherProduct, $otherAdministrator, 'purchase', 'in', '99.000', '0.000', '99.000', $start);

        $report = $this->reportFor($administrator, 'movements', [
            'outlet_id' => $outlet->id,
            'date_from' => $start->toDateString(),
            'date_to' => $start->toDateString(),
        ]);

        $this->assertSame(2, $report['detail']['movement_count']);
        $this->assertSame('2.500', $report['detail']['in_quantity']);
        $this->assertSame('0.750', $report['detail']['out_quantity']);
        $this->assertSame(['sale', 'purchase'], array_column($report['detail']['rows'], 'movement_type'));
        $this->assertSame(['Movement Outlet'], array_unique(array_column($report['detail']['rows'], 'outlet')));
    }

    public function test_stock_movement_screen_and_csv_format_money_without_converting_quantities_to_currency(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Movement CSV Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Movement CSV Warehouse');
        $product = $this->reportProduct($company, $outlet, 'Movement CSV Product', '12.34');
        $this->reportStockMovement($company, $outlet, $warehouse, $product, $administrator, 'adjustment', 'in', '1.250', '0.000', '1.250');

        $response = $this->actingAs($administrator)->get('/reports/movements?outlet_id='.$outlet->id);
        $csv = $this->actingAs($administrator)->get('/reports/movements/export?outlet_id='.$outlet->id);

        $response->assertOk()->assertSee('1.250')->assertDontSee('0.01');
        $csv->assertOk();
        $this->assertStringContainsString('1.250', $csv->streamedContent());
        $this->assertStringContainsString('12.34', $csv->streamedContent());
    }
}
