<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseRMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_r_migration_resumes_when_pack_size_and_partial_schema_already_exist(): void
    {
        $migration = require database_path('migrations/2026_08_07_010000_add_advanced_inventory_warehouse_foundation.php');
        $migration->down();

        $this->assertTrue(Schema::hasColumn('products', 'pack_size'));
        $this->assertFalse(Schema::hasColumn('products', 'track_batches'));

        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create(['code' => 'PHASE-R-COMPAT']);
        $administrator = User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => UserRole::Administrator,
        ]);
        $unit = InventoryUnit::create([
            'company_id' => $company->id,
            'name' => 'Case',
            'short_code' => 'CASE',
            'type' => 'quantity',
            'is_active' => true,
        ]);
        $warehouse = Warehouse::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Compatibility Warehouse',
            'code' => 'COMPAT-WH',
            'type' => 'store',
            'country' => 'India',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $productId = DB::table('products')->insertGetId([
            'company_id' => $company->id,
            'unit_id' => $unit->id,
            'name' => 'Existing Pack Product',
            'slug' => 'existing-pack-product',
            'sku' => 'EXISTING-PACK-001',
            'selling_price' => 499,
            'pack_size' => 24.5,
            'track_inventory' => true,
            'allow_negative_stock' => false,
            'has_variants' => false,
            'is_variant' => false,
            'status' => 'active',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stockLevelId = DB::table('stock_levels')->insertGetId([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $productId,
            'quantity_on_hand' => 8,
            'quantity_reserved' => 0,
            'quantity_available' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $registerId = DB::table('pos_registers')->insertGetId([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => 'COMPAT-REG',
            'name' => 'Compatibility Register',
            'receipt_prefix' => 'CMP',
            'is_active' => true,
            'created_by' => $administrator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('track_batches')->default(false)->after('track_inventory');
        });
        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->decimal('quantity_damaged', 14, 3)->default(0)->after('quantity_reserved');
            $table->index(['company_id', 'branch_id', 'product_id'], 'stock_level_company_branch_product_idx');
        });
        DB::table('stock_levels')->where('id', $stockLevelId)->update(['quantity_damaged' => 1.25]);

        $migration->up();
        $migration->up();

        $this->assertEquals(24.5, DB::table('products')->where('id', $productId)->value('pack_size'));
        $this->assertEquals(1.25, DB::table('stock_levels')->where('id', $stockLevelId)->value('quantity_damaged'));
        $this->assertSame($warehouse->id, DB::table('pos_registers')->where('id', $registerId)->value('warehouse_id'));
        $this->assertSame(1, DB::table('warehouses')->where('company_id', $company->id)->where('branch_id', $branch->id)->count());

        foreach (['pack_size', 'track_batches', 'track_serials', 'track_expiry'] as $column) {
            $this->assertTrue(Schema::hasColumn('products', $column), $column.' was not preserved or created.');
        }

        foreach ([
            'inventory_transfer_receipts',
            'inventory_transfer_receipt_items',
            'inventory_transfer_discrepancies',
            'inventory_stock_counts',
            'inventory_stock_count_items',
            'inventory_batches',
            'inventory_serial_numbers',
            'inventory_transfer_item_serials',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' was not created.');
        }

        $this->assertTrue(Schema::hasIndex('stock_levels', 'stock_level_company_branch_product_idx'));
        $this->assertTrue(Schema::hasIndex('pos_registers', 'pos_register_company_warehouse_idx'));
        $this->assertTrue(Schema::hasIndex('stock_movements', 'stock_move_company_state_idx'));
        $this->assertTrue(Schema::hasIndex('stock_transfers', 'stock_transfer_company_idempotency_uq', 'unique'));
        $this->assertTrue(Schema::hasIndex('stock_transfers', 'stock_transfer_company_status_eta_idx'));
        $this->assertTrue(Schema::hasIndex('reorder_rules', 'reorder_rule_location_product_uq', 'unique'));
        $this->assertFalse(Schema::hasIndex('reorder_rules', ['company_id', 'warehouse_id', 'product_id'], 'unique'));

        foreach ([
            'pos_registers' => ['warehouse_id', 'stock_location_id'],
            'stock_transfers' => ['source_stock_location_id', 'destination_stock_location_id', 'approved_by', 'packed_by', 'rejected_by'],
            'stock_transfer_items' => ['source_stock_location_id', 'destination_stock_location_id'],
            'reorder_rules' => ['stock_location_id'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasForeignKey($table, [$column]), $table.'.'.$column.' foreign key is missing.');
            }
        }
    }
}
