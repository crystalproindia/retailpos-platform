<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory\BarcodeLabelTemplate;
use App\Models\Inventory\ChannelProductMapping;
use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryTaxRate;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\ReorderRule;
use App\Models\Inventory\SalesChannel;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_is_registered_with_child_modules_and_role_filtering(): void
    {
        $registry = new ModuleRegistry;

        $inventory = $registry->find('inventory');
        $salesSidebar = $registry->sidebar(UserRole::Sales);
        $staffSidebar = $registry->sidebar(UserRole::Staff);
        $adminInventory = $registry->sidebar(UserRole::Administrator)->firstWhere('id', 'inventory');

        $this->assertSame('inventory.dashboard', $inventory->route);
        $this->assertSame('Retail Operations', $inventory->category);
        $this->assertTrue($salesSidebar->contains('id', 'inventory'));
        $this->assertFalse($staffSidebar->contains('id', 'inventory'));
        $this->assertContains('products', collect($adminInventory->children)->pluck('id'));
        $this->assertContains('inventory-stock-ledger', collect($adminInventory->children)->pluck('id'));
        $this->assertContains('inventory-channel-mapping', collect($adminInventory->children)->pluck('id'));
    }

    public function test_inventory_access_allows_sales_product_view_but_blocks_staff_and_sales_writes(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->inventoryFixtures($manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);

        $this->actingAs($sales)->get('/inventory/products')->assertOk()->assertSee($fixtures['product']->name);
        $this->actingAs($sales)->get('/inventory/products/create')->assertForbidden();
        $this->actingAs($staff)->get('/inventory/products')->assertForbidden();
    }

    public function test_product_crud_soft_delete_restore_and_tenant_isolation(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->inventoryFixtures($manager);

        $payload = $this->productPayload($fixtures, [
            'name' => 'Enterprise Demo Product',
            'sku' => 'INV-NEW-001',
            'barcode' => '890222200001',
        ]);

        $this->actingAs($manager)->post('/inventory/products', $payload)->assertRedirect();

        $product = Product::query()->where('sku', 'INV-NEW-001')->firstOrFail();

        $this->actingAs($manager)
            ->get('/inventory/products?search=INV-NEW')
            ->assertOk()
            ->assertSee('Enterprise Demo Product');

        $this->actingAs($manager)
            ->put("/inventory/products/{$product->id}", $this->productPayload($fixtures, ['name' => 'Updated Inventory Product', 'sku' => 'INV-NEW-001']))
            ->assertRedirect();

        $this->assertSame('Updated Inventory Product', $product->refresh()->name);

        $outside = $this->user(UserRole::Manager);
        $this->actingAs($outside)->get("/inventory/products/{$product->id}")->assertNotFound();

        $this->actingAs($manager)->delete("/inventory/products/{$product->id}")->assertRedirect();
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $this->actingAs($manager)->post("/inventory/products/{$product->id}/restore")->assertRedirect();
        $this->assertFalse($product->refresh()->trashed());
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.product.created']);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'inventory.product.created']);
    }

    public function test_catalog_master_data_crud_for_category_brand_unit_and_tax_rate(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)->post('/inventory/categories', ['name' => 'Accessories', 'slug' => 'accessories', 'is_active' => '1'])->assertRedirect();
        $this->actingAs($manager)->post('/inventory/brands', ['name' => 'Crystal Demo', 'slug' => 'crystal-demo', 'is_active' => '1'])->assertRedirect();
        $this->actingAs($manager)->post('/inventory/units', ['name' => 'Box', 'short_code' => 'BOX', 'type' => 'quantity', 'is_active' => '1'])->assertRedirect();
        $this->actingAs($manager)->post('/inventory/tax-rates', ['name' => 'GST 3%', 'rate' => 3, 'tax_type' => 'gst', 'country' => 'India', 'is_active' => '1'])->assertRedirect();

        $this->assertDatabaseHas('inventory_categories', ['company_id' => $manager->company_id, 'slug' => 'accessories']);
        $this->assertDatabaseHas('inventory_brands', ['company_id' => $manager->company_id, 'slug' => 'crystal-demo']);
        $this->assertDatabaseHas('inventory_units', ['company_id' => $manager->company_id, 'short_code' => 'BOX']);
        $this->assertDatabaseHas('inventory_tax_rates', ['company_id' => $manager->company_id, 'name' => 'GST 3%']);
    }

    public function test_variant_product_foundation_links_attribute_values_to_unique_variant_sku(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->inventoryFixtures($manager);

        $attribute = \App\Models\Inventory\ProductAttribute::create(['company_id' => $manager->company_id, 'name' => 'Size', 'slug' => 'size', 'type' => 'select', 'is_active' => true]);
        $value = \App\Models\Inventory\ProductAttributeValue::create(['company_id' => $manager->company_id, 'attribute_id' => $attribute->id, 'value' => 'Large', 'slug' => 'large', 'is_active' => true]);

        $this->actingAs($manager)->post('/inventory/products', $this->productPayload($fixtures, [
            'name' => 'Variant Shirt Large',
            'sku' => 'VAR-LG-001',
            'barcode' => '890222200010',
            'parent_product_id' => $fixtures['product']->id,
            'type' => 'variant',
            'is_variant' => '1',
            'attribute_value_ids' => [$value->id],
        ]))->assertRedirect();

        $variant = Product::query()->where('sku', 'VAR-LG-001')->firstOrFail();

        $this->assertTrue($variant->is_variant);
        $this->assertSame($fixtures['product']->id, $variant->parent_product_id);
        $this->assertTrue($variant->attributeValues()->whereKey($value->id)->exists());
    }

    public function test_opening_stock_updates_levels_records_ledger_and_prevents_duplicate_opening(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->inventoryFixtures($manager);

        $payload = [
            'product_id' => $fixtures['product']->id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'stock_location_id' => $fixtures['location']->id,
            'quantity' => 7,
            'unit_cost' => 120,
            'notes' => 'Opening stock test',
        ];

        $this->actingAs($manager)->post('/inventory/opening-stock', $payload)->assertRedirect();

        $this->assertDatabaseHas('stock_levels', ['product_id' => $fixtures['product']->id, 'quantity_on_hand' => 7]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $fixtures['product']->id, 'movement_type' => 'opening']);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'inventory.stock.opening_recorded']);

        $this->actingAs($manager)->post('/inventory/opening-stock', $payload)->assertSessionHasErrors('product_id');
    }

    public function test_stock_adjustment_draft_approval_and_negative_stock_guard(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->inventoryFixtures($manager);

        StockLevel::create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'stock_location_id' => $fixtures['location']->id,
            'product_id' => $fixtures['product']->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'quantity_available' => 5,
        ]);

        $this->actingAs($manager)->post('/inventory/adjustments', [
            'warehouse_id' => $fixtures['warehouse']->id,
            'reason' => 'Cycle count',
            'items' => [
                ['product_id' => $fixtures['product']->id, 'stock_location_id' => $fixtures['location']->id, 'adjusted_quantity' => 8, 'reason' => 'Counted stock'],
            ],
        ])->assertRedirect();

        $adjustment = StockAdjustment::query()->where('reason', 'Cycle count')->firstOrFail();
        $this->assertSame('draft', $adjustment->status);

        $this->actingAs($manager)->post("/inventory/adjustments/{$adjustment->id}/approve")->assertRedirect();

        $this->assertSame('approved', $adjustment->refresh()->status);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $fixtures['product']->id, 'quantity_on_hand' => 8]);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => StockAdjustment::class, 'reference_id' => $adjustment->id]);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'inventory.stock.adjusted']);

        $negative = StockAdjustment::create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'adjustment_number' => 'ADJ-NEG',
            'status' => 'draft',
            'reason' => 'Negative guard',
            'created_by' => $manager->id,
        ]);
        $negative->items()->create(['product_id' => $fixtures['product']->id, 'stock_location_id' => $fixtures['location']->id, 'current_quantity' => 8, 'adjusted_quantity' => -1, 'difference' => -9]);

        $this->actingAs($manager)->post("/inventory/adjustments/{$negative->id}/approve")->assertSessionHasErrors('items');
    }

    public function test_barcode_templates_print_batches_reorder_and_channel_mapping_foundations(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->inventoryFixtures($manager);

        $this->actingAs($manager)->post('/inventory/barcode-templates', [
            'name' => 'Shelf Label 50x25',
            'label_width_mm' => 50,
            'label_height_mm' => 25,
            'columns' => 2,
            'rows' => 10,
            'barcode_type' => 'CODE128',
            'font_size' => 10,
            'show_product_name' => '1',
            'show_sku' => '1',
            'show_price' => '1',
            'is_default' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $template = BarcodeLabelTemplate::query()->where('name', 'Shelf Label 50x25')->firstOrFail();
        $this->actingAs($manager)->post('/inventory/barcode-batches', [
            'template_id' => $template->id,
            'title' => 'Test batch',
            'items' => [['product_id' => $fixtures['product']->id, 'quantity' => 2]],
        ])->assertRedirect();

        $this->assertDatabaseHas('barcode_print_batches', ['title' => 'Test batch', 'total_labels' => 2]);

        StockLevel::create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'stock_location_id' => $fixtures['location']->id,
            'product_id' => $fixtures['product']->id,
            'quantity_on_hand' => 1,
            'quantity_available' => 1,
        ]);

        $this->actingAs($manager)->post('/inventory/reorder/rules', [
            'product_id' => $fixtures['product']->id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'minimum_stock' => 2,
            'maximum_stock' => 20,
            'reorder_point' => 3,
            'reorder_quantity' => 10,
            'safety_stock' => 1,
        ])->assertRedirect();

        $rule = ReorderRule::query()->where('product_id', $fixtures['product']->id)->firstOrFail();
        $this->assertDatabaseHas('reorder_suggestions', ['product_id' => $fixtures['product']->id, 'status' => 'pending']);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'inventory.reorder.suggested']);

        $this->actingAs($manager)->post("/inventory/reorder/rules/{$rule->id}/generate")->assertRedirect();

        $this->actingAs($manager)->post('/inventory/channels', [
            'name' => 'Website',
            'code' => 'WEB',
            'type' => 'website',
            'is_online' => '1',
            'is_active' => '1',
            'sync_enabled' => '0',
            'price_strategy' => 'selling_price',
            'stock_strategy' => 'available_stock',
        ])->assertRedirect();

        $channel = SalesChannel::query()->where('code', 'WEB')->firstOrFail();
        $this->actingAs($manager)->post('/inventory/channel-mappings', [
            'sales_channel_id' => $channel->id,
            'product_id' => $fixtures['product']->id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'channel_sku' => 'WEB-'.$fixtures['product']->sku,
            'channel_price' => 199,
            'available_quantity' => 4,
        ])->assertRedirect();

        $this->assertDatabaseHas('channel_product_mappings', ['sales_channel_id' => $channel->id, 'product_id' => $fixtures['product']->id]);
        $this->assertTrue(ChannelProductMapping::query()->where('sales_channel_id', $channel->id)->exists());
    }

    public function test_database_seeder_creates_demo_inventory_foundation_data(): void
    {
        $this->seed();

        $company = Company::query()->where('name', 'Crystal Retail Demo')->firstOrFail();

        $this->assertDatabaseHas('products', ['company_id' => $company->id, 'sku' => 'DEMO-SHOE-001']);
        $this->assertDatabaseHas('warehouses', ['company_id' => $company->id, 'code' => 'DEMO-MAIN']);
        $this->assertDatabaseHas('stock_movements', ['company_id' => $company->id, 'movement_type' => 'opening']);
        $this->assertDatabaseHas('barcode_label_templates', ['company_id' => $company->id, 'name' => 'Demo Retail Shelf Label']);
        $this->assertDatabaseHas('reorder_suggestions', ['company_id' => $company->id, 'status' => 'pending']);
        $this->assertDatabaseHas('sales_channels', ['company_id' => $company->id, 'code' => 'WEB']);
        $this->assertDatabaseHas('inventory_sync_logs', ['company_id' => $company->id, 'status' => 'warning']);
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => $role,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryFixtures(User $user): array
    {
        $category = InventoryCategory::create(['company_id' => $user->company_id, 'name' => 'Demo Category', 'slug' => 'demo-category', 'is_active' => true]);
        $brand = InventoryBrand::create(['company_id' => $user->company_id, 'name' => 'Demo Brand', 'slug' => 'demo-brand', 'is_active' => true]);
        $unit = InventoryUnit::create(['company_id' => $user->company_id, 'name' => 'Piece', 'short_code' => 'PCS', 'type' => 'quantity', 'is_active' => true]);
        $taxRate = InventoryTaxRate::create(['company_id' => $user->company_id, 'name' => 'GST 18%', 'rate' => 18, 'tax_type' => 'gst', 'country' => 'India', 'is_active' => true]);
        $warehouse = Warehouse::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'name' => 'Main Warehouse', 'code' => 'MAIN', 'type' => 'store', 'country' => 'India', 'is_active' => true]);
        $location = StockLocation::create(['company_id' => $user->company_id, 'warehouse_id' => $warehouse->id, 'name' => 'Rack A', 'code' => 'A1', 'type' => 'bin', 'is_active' => true]);
        $product = Product::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'tax_rate_id' => $taxRate->id,
            'type' => 'simple',
            'name' => 'Demo Product',
            'slug' => 'demo-product',
            'sku' => 'DEMO-001',
            'barcode' => '890111100001',
            'selling_price' => 100,
            'cost_price' => 60,
            'track_inventory' => true,
            'allow_negative_stock' => false,
            'status' => 'active',
            'is_active' => true,
        ]);

        return compact('category', 'brand', 'unit', 'taxRate', 'warehouse', 'location', 'product');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productPayload(array $fixtures, array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Demo Product Payload',
            'sku' => 'DEMO-PAYLOAD-001',
            'barcode' => '890111199999',
            'category_id' => $fixtures['category']->id,
            'brand_id' => $fixtures['brand']->id,
            'unit_id' => $fixtures['unit']->id,
            'tax_rate_id' => $fixtures['taxRate']->id,
            'type' => 'simple',
            'selling_price' => 199,
            'cost_price' => 90,
            'status' => 'active',
            'track_inventory' => '1',
        ];
    }
}
