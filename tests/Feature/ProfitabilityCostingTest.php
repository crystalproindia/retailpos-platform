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
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosReturn;
use App\Models\Pos\PosReturnItem;
use App\Models\Pos\PosSaleItem;
use App\Models\Crm\CrmInvoiceItem;
use App\Models\User;
use App\Services\Pos\PosCheckoutService;
use App\Services\Reports\PosProfitabilityBackfillService;
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

    public function test_combined_report_includes_product_linked_crm_costs_and_keeps_free_text_revenue_unavailable(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'CRM Profitability Outlet');
        $admin = $this->reportUser($company, $outlet);
        $product = $this->reportProduct($company, $outlet, 'Linked service product', '40.00');
        $invoice = $this->reportInvoice($company, $outlet, 'CRM-PROFIT-1', '200.00', '200.00');
        CrmInvoiceItem::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id, 'name' => 'Linked service product', 'sku_snapshot' => 'LINK-1',
            'quantity' => '2.000', 'unit_price' => '75.00', 'line_subtotal' => '140.00', 'line_total' => '140.00',
            'discount_amount' => '10.00', 'gross_sales_snapshot' => '150.00', 'net_sales_snapshot' => '140.00',
            'unit_cost_snapshot' => '40.00', 'total_cost_snapshot' => '80.00', 'gross_profit_snapshot' => '60.00',
            'cost_snapshot_status' => 'captured', 'cost_snapshot_method' => 'standard_cost',
        ]);
        CrmInvoiceItem::create([
            'invoice_id' => $invoice->id, 'name' => 'Custom consulting', 'quantity' => '1.000', 'unit_price' => '60.00',
            'line_subtotal' => '60.00', 'line_total' => '60.00', 'discount_amount' => '0.00', 'cost_snapshot_status' => 'unavailable',
        ]);

        $report = $this->reportFor($admin, 'profitability', ['outlet_id' => $outlet->id]);

        $this->assertSame(20000, $report['detail']['net_sales']);
        $this->assertSame(14000, $report['detail']['known_cost_net_sales']);
        $this->assertSame(8000, $report['detail']['cost_of_goods_sold']);
        $this->assertSame(6000, $report['detail']['gross_profit']);
        $this->assertSame(1, $report['detail']['unavailable_cost_item_count']);
        $this->assertSame('70.0000', $report['detail']['revenue_cost_coverage_percent']);
        $this->assertSame('crm', $report['detail']['invoice_rows'][0]['source']);
        $this->assertSame('Linked service product', $report['detail']['product_rows'][0]['dimension']);

        $posOnly = $this->reportFor($admin, 'profitability', ['outlet_id' => $outlet->id, 'source' => 'pos']);
        $this->assertSame(0, $posOnly['detail']['net_sales']);
    }

    public function test_cancelled_crm_invoices_are_excluded_from_profitability(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Cancelled CRM Outlet');
        $admin = $this->reportUser($company, $outlet);
        $invoice = $this->reportInvoice($company, $outlet, 'CRM-CANCELLED', '100.00', '100.00', 'cancelled');
        CrmInvoiceItem::create(['invoice_id' => $invoice->id, 'name' => 'Cancelled work', 'quantity' => '1.000', 'unit_price' => '100.00', 'line_subtotal' => '100.00', 'line_total' => '100.00', 'net_sales_snapshot' => '100.00', 'cost_snapshot_status' => 'unavailable']);

        $report = $this->reportFor($admin, 'profitability', ['outlet_id' => $outlet->id]);

        $this->assertSame(0, $report['detail']['net_sales']);
        $this->assertSame([], $report['detail']['invoice_rows']);
    }

    public function test_historical_pos_backfill_reconstructs_only_an_unambiguous_stock_movement_cost(): void
    {
        $company = Company::factory()->create(); $outlet = $this->reportBranch($company, 'Backfill Outlet'); $admin = $this->reportUser($company, $outlet);
        $product = $this->reportProduct($company, $outlet, 'Backfill product', '90.00'); $sale = $this->reportSale($company, $outlet, $admin, 'BACKFILL-1', '100.00');
        $item = $this->reportSaleItem($sale, $product, null, '2.000', '100.00'); $item->update(['gross_amount' => '100.00', 'taxable_amount' => '100.00']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'name' => 'Backfill WH', 'code' => 'BACKFILL-WH', 'type' => 'store', 'country' => 'India', 'is_active' => true]);
        StockMovement::create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'movement_type' => 'sale', 'direction' => 'out', 'quantity' => '2.000', 'quantity_before' => '2.000', 'quantity_after' => '0.000', 'unit_cost' => '30.00', 'reference_type' => \App\Models\Pos\PosSale::class, 'reference_id' => $sale->id, 'occurred_at' => now()]);

        $dryRun = app(PosProfitabilityBackfillService::class)->run($company->id, true);
        $this->assertSame(1, $dryRun['reconstructed']); $this->assertNull($item->fresh()->cost_snapshot_status);
        $result = app(PosProfitabilityBackfillService::class)->run($company->id, false);
        $item->refresh();
        $this->assertSame(1, $result['reconstructed']); $this->assertSame('reconstructed', $item->cost_snapshot_status); $this->assertSame('stock_movement', $item->cost_snapshot_method);
        $this->assertSame('60.00', $item->total_cost_snapshot); $this->assertSame('40.00', $item->gross_profit_snapshot);
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
