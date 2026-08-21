<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosReturn;
use App\Models\Pos\PosReturnItem;
use App\Models\Pos\PosSaleItem;
use App\Models\User;
use App\Services\Pos\PosCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class ProfitabilityCostingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_completed_pos_sale_captures_tax_exclusive_immutable_cost_and_profit_snapshots(): void
    {
        [$user, $product] = $this->checkoutFixture('100.00', '60.00');

        $sale = app(PosCheckoutService::class)->complete($user, [
            'items' => [['product_id' => $product->id, 'quantity' => '2.000']],
            'payments' => [['method' => 'cash', 'amount' => '200.00']],
            'completion_key' => 'profitability-snapshot-key',
        ]);

        $item = $sale->items()->firstOrFail();
        $this->assertSame('60.00', $item->unit_cost_snapshot);
        $this->assertSame('120.00', $item->total_cost_snapshot);
        $this->assertSame('200.00', $item->gross_sales_snapshot);
        $this->assertSame('200.00', $item->net_sales_snapshot);
        $this->assertSame('80.00', $item->gross_profit_snapshot);
        $this->assertSame('captured', $item->cost_snapshot_status);
        $this->assertSame('standard_cost', $item->cost_snapshot_method);

        $product->update(['cost_price' => '90.00']);
        $report = $this->reportFor($user, 'profitability', ['outlet_id' => $user->branch_id]);
        $this->assertSame(12000, $report['detail']['cost_of_goods_sold']);
        $this->assertSame(8000, $report['detail']['gross_profit']);
        $this->assertSame('40.0000', $report['detail']['gross_margin_percent']);
    }

    public function test_discounts_reduce_gross_profit_without_counting_gst_as_revenue(): void
    {
        [$user, $product] = $this->checkoutFixture('100.00', '70.00');
        $sale = app(PosCheckoutService::class)->complete($user, [
            'items' => [['product_id' => $product->id, 'quantity' => '1.000', 'discount_type' => 'fixed', 'discount_value' => '10.00']],
            'payments' => [['method' => 'cash', 'amount' => '90.00']],
            'completion_key' => 'profitability-discount-key',
        ]);

        $item = $sale->items()->firstOrFail();
        $this->assertSame('100.00', $item->gross_sales_snapshot);
        $this->assertSame('90.00', $item->net_sales_snapshot);
        $this->assertSame('30.00', $item->gross_profit_before_discount);
        $this->assertSame('20.00', $item->gross_profit_snapshot);

        $report = $this->reportFor($user, 'profitability', ['outlet_id' => $user->branch_id]);
        $this->assertSame(1000, $report['detail']['total_discounts']);
        $this->assertSame(1000, $report['detail']['discount_impact_on_profit']);
        $this->assertSame(2000, $report['detail']['gross_profit']);
    }

    public function test_completed_returns_reverse_original_snapshot_revenue_cost_and_profit(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Profit Return Outlet');
        $admin = $this->reportUser($company, $outlet);
        $product = $this->reportProduct($company, $outlet, 'Return product', '60.00');
        $sale = $this->reportSale($company, $outlet, $admin, 'PROFIT-RETURN', '100.00');
        $item = $this->reportSaleItem($sale, $product, null, '1.000', '100.00');
        $item->update(['gross_amount' => '100.00', 'taxable_amount' => '100.00', 'unit_cost_snapshot' => '60.00', 'total_cost_snapshot' => '60.00', 'gross_sales_snapshot' => '100.00', 'net_sales_snapshot' => '100.00', 'gross_profit_before_discount' => '40.00', 'gross_profit_snapshot' => '40.00', 'gross_margin_before_discount_percent' => '40.0000', 'gross_margin_percent_snapshot' => '40.0000', 'cost_snapshot_method' => 'standard_cost', 'cost_snapshot_status' => 'captured']);
        $return = PosReturn::create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'original_sale_id' => $sale->id, 'return_number' => 'RET-PROFIT', 'financial_year' => '2026-27', 'return_type' => 'partial_return', 'status' => PosReturn::STATUS_COMPLETED, 'return_date' => today(), 'timezone' => 'Asia/Kolkata', 'currency' => 'INR', 'refund_total' => '50.00', 'idempotency_key' => 'profitability-return-key', 'requested_by' => $admin->id, 'completed_at' => now()]);
        PosReturnItem::create(['pos_return_id' => $return->id, 'original_sale_item_id' => $item->id, 'product_id' => $product->id, 'product_name' => $product->name, 'original_quantity' => '1.000', 'previously_returned_quantity' => '0.000', 'return_quantity' => '0.500', 'unit_price_snapshot' => '100.00', 'gross_adjustment' => '50.00', 'discount_adjustment' => '0.00', 'taxable_adjustment' => '50.00', 'tax_adjustment' => '0.00', 'line_refund_total' => '50.00']);

        $report = $this->reportFor($admin, 'profitability', ['outlet_id' => $outlet->id]);
        $this->assertSame(5000, $report['detail']['net_sales']);
        $this->assertSame(3000, $report['detail']['cost_of_goods_sold']);
        $this->assertSame(2000, $report['detail']['gross_profit']);
    }

    public function test_profitability_is_management_only_and_historical_unsnapshotted_items_remain_unavailable(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Controlled Outlet');
        $admin = $this->reportUser($company, $outlet);
        $sales = $this->reportUser($company, $outlet, UserRole::Sales);
        $product = $this->reportProduct($company, $outlet, 'Historical product', '50.00');
        $sale = $this->reportSale($company, $outlet, $admin, 'HISTORICAL-NO-COST', '100.00');
        $this->reportSaleItem($sale, $product);

        $this->actingAs($admin)->get('/reports/profitability?outlet_id='.$outlet->id)->assertOk()->assertSee('historical item');
        $this->actingAs($sales)->get('/reports/profitability?outlet_id='.$outlet->id)->assertForbidden();
    }

    /** @return array{User, Product} */
    private function checkoutFixture(string $sellingPrice, string $costPrice): array
    {
        $company = Company::factory()->create();
        $outlet = Branch::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'role' => UserRole::Manager, 'is_active' => true]);
        $category = InventoryCategory::create(['company_id' => $company->id, 'name' => 'Profit category', 'slug' => 'profit-category', 'is_active' => true]);
        $brand = InventoryBrand::create(['company_id' => $company->id, 'name' => 'Profit brand', 'slug' => 'profit-brand', 'is_active' => true]);
        $unit = InventoryUnit::create(['company_id' => $company->id, 'name' => 'Piece', 'short_code' => 'PCS', 'type' => 'quantity', 'is_active' => true]);
        $product = Product::create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'category_id' => $category->id, 'brand_id' => $brand->id, 'unit_id' => $unit->id, 'name' => 'Profit Product', 'slug' => 'profit-product', 'sku' => 'PROFIT-1', 'selling_price' => $sellingPrice, 'cost_price' => $costPrice, 'track_inventory' => true, 'status' => Product::STATUS_ACTIVE, 'is_active' => true]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'name' => 'Profit warehouse', 'code' => 'PROFIT-WH', 'type' => 'store', 'country' => 'India', 'is_active' => true]);
        StockLevel::create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity_on_hand' => '10.000', 'quantity_available' => '10.000']);

        return [$user, $product];
    }
}
