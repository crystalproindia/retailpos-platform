<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSaleItem;
use App\Models\User;
use App\Services\Inventory\InventoryIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_below_minimum_stock_generates_minor_unit_safe_reorder_recommendation(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Fast Reorder', 'FAST-1', 125.50);
        $this->stock($fixture, $product, $fixture['warehouse_a'], 2, minimum: 5, maximum: 20, safety: 3);
        $this->movement($fixture, $product, $fixture['warehouse_a'], 'sale', 'out', 12, now()->subDays(10));

        $dashboard = $this->service($fixture['admin']);
        $row = $dashboard['reorder']->firstWhere('product_id', $product->id);

        $this->assertNotNull($row);
        $this->assertSame(18.0, $row['suggested_reorder_quantity']);
        $this->assertSame(225900, $row['recommended_purchase_value_minor']);
        $this->assertSame(25100, $dashboard['cards']['stock_value_minor']);
        $this->assertSame('reorder_soon', $row['health']);
        $this->assertSame('Below minimum stock', $row['reason']);
    }

    public function test_sufficient_stock_is_not_reordered_and_completed_returns_reduce_velocity(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Returned Mover', 'RETURN-1');
        $this->stock($fixture, $product, $fixture['warehouse_a'], 40, minimum: 5, maximum: 50);
        $this->movement($fixture, $product, $fixture['warehouse_a'], 'sale', 'out', 15, now()->subDays(4));
        $this->movement($fixture, $product, $fixture['warehouse_a'], 'sale_return', 'in', 7, now()->subDays(2));

        $dashboard = $this->service($fixture['admin'], ['velocity_period' => 30]);
        $row = $dashboard['rows']->firstWhere('product_id', $product->id);

        $this->assertSame(8.0, $row['units_sold']);
        $this->assertSame(0.267, $row['daily_velocity']);
        $this->assertFalse($row['needs_reorder']);
        $this->assertFalse($row['is_fast']);
        $this->assertCount(0, $dashboard['reorder']);
    }

    public function test_fast_slow_dead_new_zero_and_no_history_classifications_are_explicit(): void
    {
        $fixture = $this->fixture();
        $fast = $this->product($fixture, 'Fast', 'CLASS-FAST');
        $slow = $this->product($fixture, 'Slow', 'CLASS-SLOW');
        $dead = $this->product($fixture, 'Dead', 'CLASS-DEAD');
        $new = $this->product($fixture, 'New', 'CLASS-NEW');
        $zero = $this->product($fixture, 'Zero', 'CLASS-ZERO');
        $unknown = $this->product($fixture, 'Unknown', 'CLASS-UNKNOWN');
        foreach ([$fast, $slow, $dead, $new, $unknown] as $product) {
            $this->stock($fixture, $product, $fixture['warehouse_a'], 20);
        }
        $this->stock($fixture, $zero, $fixture['warehouse_a'], 0);
        $this->movement($fixture, $fast, $fixture['warehouse_a'], 'opening', 'in', 20, now()->subDays(200));
        $this->movement($fixture, $fast, $fixture['warehouse_a'], 'sale', 'out', 15, now()->subDays(10));
        $this->movement($fixture, $slow, $fixture['warehouse_a'], 'opening', 'in', 20, now()->subDays(100));
        $this->movement($fixture, $slow, $fixture['warehouse_a'], 'sale', 'out', 1, now()->subDays(40));
        $this->movement($fixture, $dead, $fixture['warehouse_a'], 'opening', 'in', 20, now()->subDays(200));
        $this->movement($fixture, $dead, $fixture['warehouse_a'], 'sale', 'out', 1, now()->subDays(130));
        $this->movement($fixture, $new, $fixture['warehouse_a'], 'purchase', 'in', 20, now()->subDays(5));

        $rows = $this->service($fixture['admin'])['rows']->keyBy('sku');

        $this->assertTrue($rows['CLASS-FAST']['is_fast']);
        $this->assertTrue($rows['CLASS-SLOW']['is_slow']);
        $this->assertTrue($rows['CLASS-DEAD']['is_dead']);
        $this->assertTrue($rows['CLASS-NEW']['is_new']);
        $this->assertFalse($rows['CLASS-NEW']['is_slow']);
        $this->assertTrue($rows['CLASS-ZERO']['is_out']);
        $this->assertTrue($rows['CLASS-UNKNOWN']['is_dead']);
        $this->assertNull($rows['CLASS-UNKNOWN']['stock_age_days']);
    }

    public function test_aging_uses_latest_inbound_evidence_and_reconciles_stock_value(): void
    {
        $fixture = $this->fixture();
        $recent = $this->product($fixture, 'Recent', 'AGE-RECENT', 10);
        $aged = $this->product($fixture, 'Aged', 'AGE-OLD', 20);
        $unknown = $this->product($fixture, 'Unknown', 'AGE-UNKNOWN', 5);
        $this->stock($fixture, $recent, $fixture['warehouse_a'], 2);
        $this->stock($fixture, $aged, $fixture['warehouse_a'], 3);
        $this->stock($fixture, $unknown, $fixture['warehouse_a'], 4);
        $this->movement($fixture, $recent, $fixture['warehouse_a'], 'purchase', 'in', 2, now()->subDays(15));
        $this->movement($fixture, $aged, $fixture['warehouse_a'], 'opening', 'in', 3, now()->subDays(190));

        $dashboard = $this->service($fixture['admin']);
        $aging = collect($dashboard['aging'])->keyBy('label');

        $this->assertSame(2.0, $aging['0-30 days']['quantity']);
        $this->assertSame(2000, $aging['0-30 days']['value_minor']);
        $this->assertSame(3.0, $aging['180+ days']['quantity']);
        $this->assertSame(6000, $aging['180+ days']['value_minor']);
        $this->assertSame(4.0, $aging['Unknown']['quantity']);
        $this->assertSame($dashboard['cards']['stock_value_minor'], collect($dashboard['aging'])->sum('value_minor'));
    }

    public function test_transfer_recommendation_preserves_source_safety_and_never_crosses_tenant_or_same_warehouse(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Balance Me', 'MOVE-1');
        $this->stock($fixture, $product, $fixture['warehouse_a'], 30, minimum: 5, maximum: 20, safety: 8);
        $this->stock($fixture, $product, $fixture['warehouse_b'], 2, minimum: 5, maximum: 20, safety: 3);
        $this->movement($fixture, $product, $fixture['warehouse_b'], 'sale', 'out', 12, now()->subDays(10));
        $outside = $this->fixture();
        $outsideProduct = $this->product($outside, 'Outside', 'MOVE-OUT');
        $this->stock($outside, $outsideProduct, $outside['warehouse_a'], 999, minimum: 1, maximum: 2);

        $transfer = $this->service($fixture['admin'])['transfers']->sole();

        $this->assertSame($fixture['warehouse_a']->id, $transfer['source_warehouse_id']);
        $this->assertSame($fixture['warehouse_b']->id, $transfer['destination_warehouse_id']);
        $this->assertSame(10.0, $transfer['suggested_quantity']);
        $this->assertGreaterThanOrEqual($transfer['source_safety_stock'], $transfer['source_stock'] - $transfer['suggested_quantity']);
        $this->assertNotSame($transfer['source_warehouse_id'], $transfer['destination_warehouse_id']);
        $this->assertSame('MOVE-1', $transfer['sku']);
    }

    public function test_manager_scope_hides_unassigned_outlet_and_prevents_transfer_disclosure(): void
    {
        $fixture = $this->fixture();
        $manager = User::factory()->for($fixture['company'])->create(['branch_id' => $fixture['branch_a']->id, 'role' => UserRole::Manager]);
        BranchUserAssignment::create(['company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch_a']->id, 'user_id' => $manager->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $fixture['admin']->id]);
        $product = $this->product($fixture, 'Scoped', 'SCOPE-1');
        $this->stock($fixture, $product, $fixture['warehouse_a'], 30, minimum: 5, maximum: 20);
        $this->stock($fixture, $product, $fixture['warehouse_b'], 1, minimum: 5, maximum: 20);

        $dashboard = $this->service($manager);

        $this->assertSame(1, $dashboard['scope_count']);
        $this->assertCount(1, $dashboard['rows']);
        $this->assertSame($fixture['warehouse_a']->id, $dashboard['rows']->first()['warehouse_id']);
        $this->assertCount(0, $dashboard['transfers']);
        $this->actingAs($manager)->get(route('inventory.intelligence.index', ['warehouse_id' => $fixture['warehouse_b']->id]))->assertForbidden();
    }

    public function test_authorized_outlet_without_a_warehouse_returns_a_safe_empty_owner_signal(): void
    {
        $company = Company::factory()->create();
        $outlet = Branch::factory()->for($company)->create(['is_active' => true]);
        $admin = User::factory()->for($company)->create(['branch_id' => $outlet->id, 'role' => UserRole::Administrator]);

        $dashboard = $this->service($admin, ['outlet_id' => $outlet->id]);

        $this->assertSame(0, $dashboard['scope_count']);
        $this->assertSame(0, $dashboard['cards']['stock_value_minor']);
        $this->assertCount(0, $dashboard['rows']);
    }

    public function test_page_permissions_navigation_and_legacy_decision_route_are_safe(): void
    {
        $fixture = $this->fixture();
        $staff = User::factory()->for($fixture['company'])->create(['branch_id' => $fixture['branch_a']->id, 'role' => UserRole::Staff]);

        $this->actingAs($fixture['admin'])->get(route('inventory.intelligence.index'))->assertOk()->assertSee('Know what to buy, move, and review');
        $this->get(route('inventory.decision-dashboard'))->assertOk()->assertSee('Inventory Intelligence');
        $this->actingAs($staff)->get(route('inventory.intelligence.index'))->assertForbidden();
    }

    public function test_transfer_recommendation_prefill_is_authorized_and_inventory_thresholds_are_configurable(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Prefill Product', 'PREFILL-1');

        $this->actingAs($fixture['admin'])->get(route('inventory.transfers.create', [
            'source_warehouse_id' => $fixture['warehouse_a']->id,
            'destination_warehouse_id' => $fixture['warehouse_b']->id,
            'product_id' => $product->id,
            'quantity' => 4,
        ]))->assertOk()->assertSee('PREFILL-1')->assertSee('4');

        $this->put(route('inventory.settings.update'), [
            'default_cost_method' => 'weighted_average', 'barcode_price_source' => 'selling_price',
            'dead_stock_days' => 180, 'new_stock_grace_days' => 21, 'slow_mover_max_units' => 3,
            'fast_mover_min_units' => 12, 'fast_mover_min_daily_velocity' => 0.5,
            'default_lead_time_days' => 10, 'large_adjustment_threshold' => 100,
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('settings', ['company_id' => $fixture['company']->id, 'group' => 'inventory', 'key' => 'dead_stock_days']);

        $outside = $this->fixture();
        $this->get(route('inventory.transfers.create', [
            'source_warehouse_id' => $fixture['warehouse_a']->id,
            'destination_warehouse_id' => $outside['warehouse_a']->id,
            'product_id' => $product->id,
            'quantity' => 4,
        ]))->assertForbidden();
    }

    public function test_exports_use_same_scope_and_escape_spreadsheet_formulas(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, '=Formula Product', 'CSV-1');
        $this->stock($fixture, $product, $fixture['warehouse_a'], 1, minimum: 5, maximum: 10);

        $response = $this->actingAs($fixture['admin'])->get(route('inventory.intelligence.export', ['dataset' => 'reorder', 'warehouse_id' => $fixture['warehouse_a']->id]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=Formula Product", $csv);
        $this->assertStringContainsString('CSV-1', $csv);
        $this->assertStringNotContainsString($fixture['warehouse_b']->name, $csv);
    }

    public function test_profitability_insight_uses_immutable_sale_snapshot_and_never_fabricates_missing_margin(): void
    {
        $fixture = $this->fixture();
        $known = $this->product($fixture, 'Known Margin', 'PROFIT-1', 60);
        $unknown = $this->product($fixture, 'No Profit Evidence', 'PROFIT-2', 25);
        $this->stock($fixture, $known, $fixture['warehouse_a'], 2, minimum: 5, maximum: 20);
        $this->stock($fixture, $unknown, $fixture['warehouse_a'], 20);
        $sale = PosSale::create(['company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch_a']->id, 'sale_number' => 'INTEL-SALE-1', 'receipt_number' => 'INTEL-REC-1', 'status' => 'completed', 'currency' => 'INR', 'subtotal' => 100, 'taxable_amount' => 100, 'total_amount' => 100, 'completed_by' => $fixture['admin']->id, 'sold_at' => now()->subDays(2), 'completed_at' => now()->subDays(2)]);
        PosSaleItem::create(['company_id' => $fixture['company']->id, 'pos_sale_id' => $sale->id, 'product_id' => $known->id, 'category_id' => $fixture['category']->id, 'brand_id_snapshot' => $fixture['brand']->id, 'product_name' => $known->name, 'sku' => $known->sku, 'quantity' => 1, 'unit_price' => 100, 'unit_cost_snapshot' => 60, 'total_cost_snapshot' => 60, 'gross_amount' => 100, 'gross_sales_snapshot' => 100, 'net_sales_snapshot' => 100, 'gross_profit_snapshot' => 40, 'cost_snapshot_method' => 'standard_cost', 'cost_snapshot_status' => 'captured', 'taxable_amount' => 100, 'line_total' => 100]);
        $this->movement($fixture, $known, $fixture['warehouse_a'], 'sale', 'out', 1, now()->subDays(2));

        $before = $this->service($fixture['admin'])['rows']->keyBy('sku');
        $known->update(['cost_price' => 95]);
        $after = $this->service($fixture['admin'])['rows']->keyBy('sku');

        $this->assertSame(4000, $before['PROFIT-1']['gross_profit_minor']);
        $this->assertSame('40.0000', $before['PROFIT-1']['margin_percent']);
        $this->assertSame($before['PROFIT-1']['gross_profit_minor'], $after['PROFIT-1']['gross_profit_minor']);
        $this->assertSame($before['PROFIT-1']['margin_percent'], $after['PROFIT-1']['margin_percent']);
        $this->assertNull($after['PROFIT-2']['margin_percent']);
        $this->assertSame(0, $after['PROFIT-2']['gross_profit_minor']);
    }

    private function service(User $user, array $filters = []): array
    {
        return app(InventoryIntelligenceService::class)->dashboard($user, $filters);
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata']);
        $branchA = Branch::factory()->for($company)->create(['name' => 'Outlet A', 'is_active' => true]);
        $branchB = Branch::factory()->for($company)->create(['name' => 'Outlet B', 'is_active' => true]);
        $admin = User::factory()->for($company)->create(['branch_id' => $branchA->id, 'role' => UserRole::Administrator]);
        $unit = InventoryUnit::create(['company_id' => $company->id, 'name' => 'Piece', 'short_code' => 'PCS', 'is_active' => true]);
        $category = InventoryCategory::create(['company_id' => $company->id, 'name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $brand = InventoryBrand::create(['company_id' => $company->id, 'name' => 'Retail', 'slug' => 'retail', 'is_active' => true]);
        $warehouseA = Warehouse::create(['company_id' => $company->id, 'branch_id' => $branchA->id, 'name' => 'Outlet A Stock', 'code' => 'A', 'is_active' => true]);
        $warehouseB = Warehouse::create(['company_id' => $company->id, 'branch_id' => $branchB->id, 'name' => 'Outlet B Stock', 'code' => 'B', 'is_active' => true]);

        return compact('company', 'admin', 'unit', 'category', 'brand') + ['branch_a' => $branchA, 'branch_b' => $branchB, 'warehouse_a' => $warehouseA, 'warehouse_b' => $warehouseB];
    }

    private function product(array $fixture, string $name, string $sku, float $cost = 10): Product
    {
        return Product::create(['company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch_a']->id, 'category_id' => $fixture['category']->id, 'brand_id' => $fixture['brand']->id, 'unit_id' => $fixture['unit']->id, 'name' => $name, 'slug' => strtolower(str_replace(['=', ' '], ['', '-'], $sku)).'-'.uniqid(), 'sku' => $sku, 'cost_price' => $cost, 'selling_price' => $cost * 2, 'track_inventory' => true, 'status' => 'active', 'is_active' => true]);
    }

    private function stock(array $fixture, Product $product, Warehouse $warehouse, float $quantity, float $minimum = 5, float $maximum = 20, float $safety = 2): StockLevel
    {
        return StockLevel::create(['company_id' => $fixture['company']->id, 'branch_id' => $warehouse->branch_id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity_on_hand' => $quantity, 'quantity_available' => $quantity, 'minimum_stock' => $minimum, 'maximum_stock' => $maximum, 'reorder_point' => $minimum, 'reorder_quantity' => max(1, $maximum - $minimum), 'safety_stock' => $safety, 'supplier_lead_time_days' => 7]);
    }

    private function movement(array $fixture, Product $product, Warehouse $warehouse, string $type, string $direction, float $quantity, $when): StockMovement
    {
        return StockMovement::create(['company_id' => $fixture['company']->id, 'branch_id' => $warehouse->branch_id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'movement_type' => $type, 'direction' => $direction, 'quantity' => $quantity, 'quantity_before' => 0, 'quantity_after' => $direction === 'in' ? $quantity : 0, 'unit_cost' => $product->cost_price, 'created_by' => $fixture['admin']->id, 'occurred_at' => $when]);
    }
}
