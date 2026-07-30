<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class InventoryReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_stock_on_hand_rows_reconcile_to_current_balances_with_outlet_and_warehouse_scoping(): void
    {
        $company = Company::factory()->create();
        $first = $this->reportBranch($company, 'First Inventory Outlet');
        $second = $this->reportBranch($company, 'Second Inventory Outlet');
        $administrator = $this->reportUser($company, $first);
        $firstWarehouse = $this->reportWarehouse($company, $first, 'First Inventory Warehouse');
        $secondWarehouse = $this->reportWarehouse($company, $second, 'Second Inventory Warehouse');
        $firstProduct = $this->reportProduct($company, $first, 'First Stock Product', '10.00');
        $secondProduct = $this->reportProduct($company, $second, 'Second Stock Product', '10.00');

        $this->reportStockLevel($company, $first, $firstWarehouse, $firstProduct, '2.500', '3.000');
        $this->reportStockLevel($company, $second, $secondWarehouse, $secondProduct, '4.000', '2.000');

        $all = $this->reportFor($administrator, 'inventory', ['outlet_id' => 'all']);
        $firstOnly = $this->reportFor($administrator, 'inventory', ['outlet_id' => 'all', 'warehouse_id' => $firstWarehouse->id]);

        $this->assertSame(6500, $all['detail']['value']);
        $this->assertSame(1, $all['detail']['low_stock_count']);
        $this->assertSame(2500, $firstOnly['detail']['value']);
        $this->assertSame(['First Stock Product'], array_column($firstOnly['detail']['rows'], 'product'));
    }

    public function test_transfer_source_and_destination_balances_are_neutral_for_company_totals_and_visible_by_outlet(): void
    {
        $company = Company::factory()->create();
        $source = $this->reportBranch($company, 'Transfer Source Outlet');
        $destination = $this->reportBranch($company, 'Transfer Destination Outlet');
        $administrator = $this->reportUser($company, $source);
        $sourceWarehouse = $this->reportWarehouse($company, $source, 'Transfer Source Warehouse');
        $destinationWarehouse = $this->reportWarehouse($company, $destination, 'Transfer Destination Warehouse');
        $product = $this->reportProduct($company, $source, 'Transferred Product', '5.00');

        $this->reportStockLevel($company, $source, $sourceWarehouse, $product, '6.000');
        $this->reportStockLevel($company, $destination, $destinationWarehouse, $product, '4.000');

        $report = $this->reportFor($administrator, 'inventory', ['outlet_id' => 'all']);

        $this->assertSame(5000, $report['detail']['value']);
        $this->assertSame(2, count($report['detail']['rows']));
        $this->assertEqualsCanonicalizing(['Transfer Source Outlet', 'Transfer Destination Outlet'], array_column($report['detail']['rows'], 'outlet'));
    }

    public function test_adjustment_and_purchase_return_effects_are_represented_by_the_current_stock_balance_without_float_rounding(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Adjustment Inventory Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Adjustment Inventory Warehouse');
        $supplier = $this->reportSupplier($company, 'Adjustment Supplier');
        $product = $this->reportProduct($company, $outlet, 'Adjustment Product', '19.99');
        $level = $this->reportStockLevel($company, $outlet, $warehouse, $product, '10.000');

        $level->update(['quantity_on_hand' => '7.250', 'quantity_available' => '7.250']);
        $this->reportPurchaseReturn($company, $outlet, $warehouse, $supplier, $product, $administrator, 'PRET-STOCK', '2.750', '19.99');

        $report = $this->reportFor($administrator, 'inventory');

        $this->assertSame(14493, $report['detail']['value']);
        $this->assertSame('7.250', $report['detail']['rows'][0]['quantity']);
        $this->assertSame(1999, $report['detail']['rows'][0]['unit_cost']);
    }

    public function test_negative_stock_and_archived_outlet_history_are_visible_only_to_company_wide_administrators(): void
    {
        $company = Company::factory()->create();
        $active = $this->reportBranch($company, 'Active Inventory Outlet');
        $archived = $this->reportBranch($company, 'Archived Inventory Outlet');
        $archived->update(['is_active' => false]);
        $administrator = $this->reportUser($company, $active);
        $manager = $this->reportUser($company, $active, UserRole::Manager);
        $activeWarehouse = $this->reportWarehouse($company, $active, 'Active Inventory Warehouse');
        $archivedWarehouse = $this->reportWarehouse($company, $archived, 'Archived Inventory Warehouse');
        $activeProduct = $this->reportProduct($company, $active, 'Active Inventory Product', '10.00');
        $archivedProduct = $this->reportProduct($company, $archived, 'Archived Inventory Product', '10.00');

        $this->reportStockLevel($company, $active, $activeWarehouse, $activeProduct, '1.000');
        $this->reportStockLevel($company, $archived, $archivedWarehouse, $archivedProduct, '-2.000');

        $companyWide = $this->reportFor($administrator, 'inventory', ['outlet_id' => 'all']);

        $this->assertSame(-1000, $companyWide['detail']['value']);
        $this->assertEqualsCanonicalizing(['Active Inventory Outlet', 'Archived Inventory Outlet'], array_column($companyWide['detail']['rows'], 'outlet'));
        $this->actingAs($manager)->get('/reports/inventory?outlet_id=all')->assertSessionHasErrors('outlet_id');
    }
}
