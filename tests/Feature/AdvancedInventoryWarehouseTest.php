<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory\BarcodeLabelTemplate;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventorySerialNumber;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Services\Inventory\BarcodeRenderer;
use App\Services\Inventory\BarcodeService;
use App\Services\Inventory\InventoryLocationAccessService;
use App\Services\Inventory\InventoryStockViewService;
use App\Services\Inventory\InventoryTraceabilityService;
use App\Services\Inventory\StockCountService;
use App\Services\Inventory\StockService;
use App\Services\Inventory\StockTransferService;
use App\Services\Pos\PosRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdvancedInventoryWarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_supported_location_combinations_create_explicit_transfer_scopes(): void
    {
        $fixture = $this->fixture();
        $service = app(StockTransferService::class);
        $pairs = [
            [$fixture['store_a'], $fixture['store_b']],
            [$fixture['central_a'], $fixture['store_a']],
            [$fixture['store_a'], $fixture['central_a']],
            [$fixture['central_a'], $fixture['central_b']],
        ];

        foreach ($pairs as $index => [$source, $destination]) {
            $transfer = $service->create($fixture['admin'], [
                'source_warehouse_id' => $source->id,
                'destination_warehouse_id' => $destination->id,
                'idempotency_key' => 'location-pair-'.$index,
                'items' => [['product_id' => $fixture['product']->id, 'quantity' => 1]],
            ]);

            $this->assertSame($source->id, $transfer->source_warehouse_id);
            $this->assertSame($destination->id, $transfer->destination_warehouse_id);
            $this->assertSame(StockTransfer::DRAFT, $transfer->status);
        }

        $this->assertSame(4, StockTransfer::query()->count());
        $this->assertDatabaseCount('stock_movements', 0);
        $this->actingAs($fixture['admin'])
            ->get(route('inventory.transfers.show', $transfer))
            ->assertOk()
            ->assertSee($transfer->transfer_number)
            ->assertSee('Ready to request this transfer?');
    }

    public function test_dispatch_partial_receipt_and_final_receipt_reconcile_source_transit_and_destination(): void
    {
        $fixture = $this->fixture();
        $this->stock($fixture, $fixture['store_a'], 10);
        $service = app(StockTransferService::class);
        $transfer = $this->readyTransfer($fixture, $service, 6);

        $service->dispatch($transfer, $fixture['admin']);

        $this->assertSame('4.000', $this->level($fixture, $fixture['store_a'])->quantity_on_hand);
        $this->assertSame(0, StockLevel::query()->where('warehouse_id', $fixture['store_b']->id)->count());
        $this->assertSame('6.000', $transfer->refresh()->items->first()->in_transit_quantity);

        $item = $transfer->items->first();
        $service->receive($transfer, $fixture['admin'], [
            'idempotency_key' => 'partial-receipt',
            'items' => [['id' => $item->id, 'received_quantity' => 4, 'damaged_quantity' => 0, 'short_quantity' => 0]],
        ]);

        $this->assertSame(StockTransfer::PARTIALLY_RECEIVED, $transfer->refresh()->status);
        $this->assertSame('2.000', $transfer->items->first()->in_transit_quantity);
        $this->assertSame('4.000', $this->level($fixture, $fixture['store_b'])->quantity_available);

        $service->receive($transfer, $fixture['admin'], [
            'idempotency_key' => 'final-receipt',
            'items' => [['id' => $item->id, 'received_quantity' => 2, 'damaged_quantity' => 0, 'short_quantity' => 0]],
        ]);
        $service->receive($transfer, $fixture['admin'], ['idempotency_key' => 'final-receipt']);

        $this->assertSame(StockTransfer::RECEIVED, $transfer->refresh()->status);
        $this->assertSame('6.000', $this->level($fixture, $fixture['store_b'])->quantity_available);
        $this->assertDatabaseCount('inventory_transfer_receipts', 2);
        $this->assertDatabaseHas('stock_movements', ['reference_id' => $transfer->id, 'movement_type' => 'transfer_dispatch', 'from_stock_state' => 'available', 'to_stock_state' => 'in_transit']);
        $this->assertSame('6.000', (string) $transfer->items->first()->received_quantity);
    }

    public function test_damage_and_shortage_remain_auditable_until_resolution(): void
    {
        $fixture = $this->fixture();
        $this->stock($fixture, $fixture['store_a'], 8);
        $service = app(StockTransferService::class);
        $transfer = $this->readyTransfer($fixture, $service, 5);
        $service->dispatch($transfer, $fixture['admin']);
        $item = $transfer->refresh()->items->first();

        $service->receive($transfer, $fixture['admin'], [
            'items' => [['id' => $item->id, 'received_quantity' => 2, 'damaged_quantity' => 1, 'short_quantity' => 2, 'notes' => 'Carton damaged and two units missing']],
        ]);

        $this->assertSame(StockTransfer::DISCREPANCY, $transfer->refresh()->status);
        $this->assertSame('2.000', $transfer->items->first()->in_transit_quantity);
        $this->assertSame('2.000', $this->level($fixture, $fixture['store_b'])->quantity_available);
        $this->assertSame('1.000', $this->level($fixture, $fixture['store_b'])->quantity_damaged);
        $this->assertDatabaseHas('inventory_transfer_discrepancies', ['stock_transfer_id' => $transfer->id, 'type' => 'damaged_in_transit', 'status' => 'open']);
        $shortage = $transfer->discrepancies()->where('type', 'short_received')->firstOrFail();
        $service->resolveDiscrepancy($shortage, $fixture['admin'], 'restock_source', 'Carrier confirmed return to source.');

        $this->assertSame('5.000', $this->level($fixture, $fixture['store_a'])->quantity_available);
        $this->assertSame('0.000', $transfer->refresh()->items->first()->in_transit_quantity);
        $this->assertDatabaseHas('inventory_transfer_discrepancies', ['id' => $shortage->id, 'status' => 'resolved', 'resolution' => 'restock_source']);
    }

    public function test_transfer_rejects_overdraw_cross_tenant_and_duplicate_dispatch(): void
    {
        $fixture = $this->fixture();
        $level = $this->stock($fixture, $fixture['store_a'], 3);
        $service = app(StockTransferService::class);
        $transfer = $this->readyTransfer($fixture, $service, 3);
        $level->update(['quantity_on_hand' => 2, 'quantity_available' => 2]);

        try {
            $service->dispatch($transfer, $fixture['admin']);
            $this->fail('An overdrawn transfer was dispatched.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $transfer->items()->update(['approved_quantity' => 2, 'packed_quantity' => 2]);
        $service->dispatch($transfer, $fixture['admin']);
        $service->dispatch($transfer, $fixture['admin']);
        $this->assertSame('0.000', $this->level($fixture, $fixture['store_a'])->quantity_available);
        $this->assertSame(1, StockMovement::query()->where('reference_type', StockTransfer::class)->where('reference_id', $transfer->id)->where('movement_type', 'transfer_dispatch')->count());

        $outside = $this->fixture();
        $this->expectException(ValidationException::class);
        $service->create($outside['admin'], [
            'source_warehouse_id' => $fixture['store_a']->id,
            'destination_warehouse_id' => $outside['store_a']->id,
            'items' => [['product_id' => $outside['product']->id, 'quantity' => 1]],
        ]);
    }

    public function test_cancelled_serial_transfer_releases_reservations(): void
    {
        $fixture = $this->fixture(['track_serials' => true]);
        $this->stock($fixture, $fixture['store_a'], 1);
        $serial = InventorySerialNumber::create([
            'company_id' => $fixture['company']->id,
            'product_id' => $fixture['product']->id,
            'warehouse_id' => $fixture['store_a']->id,
            'serial_number' => 'SERIAL-001',
            'status' => 'available',
        ]);
        $service = app(StockTransferService::class);
        $transfer = $service->create($fixture['admin'], [
            'source_warehouse_id' => $fixture['store_a']->id,
            'destination_warehouse_id' => $fixture['store_b']->id,
            'items' => [['product_id' => $fixture['product']->id, 'quantity' => 1, 'serial_ids' => [$serial->id]]],
        ]);

        $this->assertSame('reserved', $serial->refresh()->status);
        $service->cancel($transfer, $fixture['admin'], 'Customer allocation changed.');

        $this->assertSame('available', $serial->refresh()->status);
        $this->assertDatabaseHas('inventory_transfer_item_serials', ['inventory_serial_number_id' => $serial->id, 'status' => 'released']);
    }

    public function test_batch_and_serial_identity_follow_a_completed_transfer(): void
    {
        $fixture = $this->fixture(['track_batches' => true, 'track_serials' => true, 'track_expiry' => true]);
        $this->stock($fixture, $fixture['store_a'], 1);
        $batch = InventoryBatch::create([
            'company_id' => $fixture['company']->id,
            'product_id' => $fixture['product']->id,
            'warehouse_id' => $fixture['store_a']->id,
            'batch_number' => 'BATCH-A',
            'expires_at' => today()->addDays(30),
            'quantity_on_hand' => 1,
            'quantity_available' => 1,
            'status' => 'active',
        ]);
        $serial = InventorySerialNumber::create([
            'company_id' => $fixture['company']->id,
            'product_id' => $fixture['product']->id,
            'inventory_batch_id' => $batch->id,
            'warehouse_id' => $fixture['store_a']->id,
            'serial_number' => 'SERIAL-BATCH-A',
            'status' => 'available',
        ]);
        $service = app(StockTransferService::class);
        $transfer = $service->create($fixture['admin'], [
            'source_warehouse_id' => $fixture['store_a']->id,
            'destination_warehouse_id' => $fixture['store_b']->id,
            'items' => [['product_id' => $fixture['product']->id, 'quantity' => 1, 'inventory_batch_id' => $batch->id, 'serial_ids' => [$serial->id]]],
        ]);
        $service->submit($transfer, $fixture['admin']);
        $item = $transfer->refresh()->items->first();
        $service->approve($transfer, $fixture['admin'], [['id' => $item->id, 'approved_quantity' => 1]]);
        $service->pack($transfer, $fixture['admin'], [['id' => $item->id, 'packed_quantity' => 1]]);
        $service->dispatch($transfer, $fixture['admin']);
        $service->receive($transfer, $fixture['admin'], ['items' => [['id' => $item->id, 'received_quantity' => 1, 'serial_ids' => [$serial->id]]]]);

        $this->assertSame('0.000', $batch->refresh()->quantity_available);
        $this->assertDatabaseHas('inventory_batches', ['warehouse_id' => $fixture['store_b']->id, 'batch_number' => 'BATCH-A', 'quantity_available' => 1]);
        $this->assertSame('available', $serial->refresh()->status);
        $this->assertSame($fixture['store_b']->id, $serial->warehouse_id);
        $this->assertDatabaseHas('inventory_transfer_item_serials', ['inventory_serial_number_id' => $serial->id, 'status' => 'received']);
    }

    public function test_damaged_receipt_preserves_batch_identity_without_saleable_quantity(): void
    {
        $fixture = $this->fixture(['track_batches' => true, 'track_expiry' => true]);
        $this->stock($fixture, $fixture['store_a'], 3);
        $batch = InventoryBatch::create([
            'company_id' => $fixture['company']->id,
            'product_id' => $fixture['product']->id,
            'warehouse_id' => $fixture['store_a']->id,
            'batch_number' => 'DAMAGED-BATCH',
            'expires_at' => today()->addDays(45),
            'quantity_on_hand' => 3,
            'quantity_available' => 3,
            'status' => 'active',
        ]);
        $service = app(StockTransferService::class);
        $transfer = $service->create($fixture['admin'], [
            'source_warehouse_id' => $fixture['store_a']->id,
            'destination_warehouse_id' => $fixture['store_b']->id,
            'items' => [['product_id' => $fixture['product']->id, 'inventory_batch_id' => $batch->id, 'quantity' => 2]],
        ]);
        $service->submit($transfer, $fixture['admin']);
        $item = $transfer->refresh()->items->first();
        $service->approve($transfer, $fixture['admin'], [['id' => $item->id, 'approved_quantity' => 2]]);
        $service->pack($transfer, $fixture['admin'], [['id' => $item->id, 'packed_quantity' => 2]]);
        $service->dispatch($transfer, $fixture['admin']);
        $service->receive($transfer, $fixture['admin'], ['items' => [['id' => $item->id, 'damaged_quantity' => 2]]]);

        $this->assertDatabaseHas('inventory_batches', [
            'warehouse_id' => $fixture['store_b']->id,
            'batch_number' => 'DAMAGED-BATCH',
            'quantity_on_hand' => 2,
            'quantity_available' => 0,
        ]);
        $this->assertSame('2.000', $this->level($fixture, $fixture['store_b'])->quantity_damaged);
    }

    public function test_traceability_rejects_duplicate_serials_and_batch_overallocation(): void
    {
        $fixture = $this->fixture(['track_batches' => true, 'track_serials' => true]);
        $this->stock($fixture, $fixture['store_a'], 2);
        $service = app(InventoryTraceabilityService::class);
        $service->createSerials($fixture['admin'], ['warehouse_id' => $fixture['store_a']->id, 'product_id' => $fixture['product']->id, 'serial_numbers' => "SER-A\nSER-B"]);
        $this->assertDatabaseCount('inventory_serial_numbers', 2);

        try {
            $service->createSerials($fixture['admin'], ['warehouse_id' => $fixture['store_a']->id, 'product_id' => $fixture['product']->id, 'serial_numbers' => 'SER-A']);
            $this->fail('A duplicate serial was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('serial_numbers', $exception->errors());
        }

        $service->saveBatch($fixture['admin'], ['warehouse_id' => $fixture['store_a']->id, 'product_id' => $fixture['product']->id, 'batch_number' => 'B1', 'quantity_on_hand' => 2]);
        $this->expectException(ValidationException::class);
        $service->saveBatch($fixture['admin'], ['warehouse_id' => $fixture['store_a']->id, 'product_id' => $fixture['product']->id, 'batch_number' => 'B2', 'quantity_on_hand' => 1]);
    }

    public function test_physical_count_does_not_mutate_until_post_and_rejects_stale_snapshot(): void
    {
        $fixture = $this->fixture();
        $level = $this->stock($fixture, $fixture['store_a'], 10);
        $service = app(StockCountService::class);
        $count = $service->create($fixture['admin'], ['warehouse_id' => $fixture['store_a']->id, 'type' => 'selected', 'product_ids' => [$fixture['product']->id]]);
        $item = $count->items->first();
        $service->record($count, $fixture['admin'], [['id' => $item->id, 'counted_quantity' => 8]]);
        $service->submit($count, $fixture['admin']);
        $service->approve($count, $fixture['admin']);

        $this->assertSame('10.000', $level->refresh()->quantity_on_hand);
        $service->post($count, $fixture['admin']);
        $this->assertSame('8.000', $level->refresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', ['reference_id' => $count->id, 'movement_type' => 'physical_count', 'quantity_before' => 10, 'quantity_after' => 8]);

        $stale = $service->create($fixture['admin'], ['warehouse_id' => $fixture['store_a']->id, 'type' => 'cycle', 'product_ids' => [$fixture['product']->id]]);
        $staleItem = $stale->items->first();
        $service->record($stale, $fixture['admin'], [['id' => $staleItem->id, 'counted_quantity' => 7]]);
        $service->submit($stale, $fixture['admin']);
        $service->approve($stale, $fixture['admin']);
        $level->update(['quantity_on_hand' => 9, 'quantity_available' => 9]);

        $this->expectException(ValidationException::class);
        $service->post($stale, $fixture['admin']);
    }

    public function test_warehouse_count_snapshots_and_posts_each_physical_bin_separately(): void
    {
        $fixture = $this->fixture();
        $binA = StockLocation::create(['company_id' => $fixture['company']->id, 'warehouse_id' => $fixture['store_a']->id, 'name' => 'Aisle A', 'code' => 'A-01', 'type' => 'bin', 'is_active' => true]);
        $binB = StockLocation::create(['company_id' => $fixture['company']->id, 'warehouse_id' => $fixture['store_a']->id, 'name' => 'Aisle B', 'code' => 'B-01', 'type' => 'bin', 'is_active' => true]);
        foreach ([[$binA, 3], [$binB, 7]] as [$location, $quantity]) {
            StockLevel::create([
                'company_id' => $fixture['company']->id,
                'branch_id' => $fixture['branch_a']->id,
                'warehouse_id' => $fixture['store_a']->id,
                'stock_location_id' => $location->id,
                'product_id' => $fixture['product']->id,
                'quantity_on_hand' => $quantity,
                'quantity_available' => $quantity,
            ]);
        }
        $service = app(StockCountService::class);
        $count = $service->create($fixture['admin'], ['warehouse_id' => $fixture['store_a']->id, 'type' => 'warehouse']);

        $this->assertCount(2, $count->items);
        $this->assertSame(10.0, (float) $count->items->sum('system_quantity'));
        $this->assertEqualsCanonicalizing([$binA->id, $binB->id], $count->items->pluck('stock_location_id')->all());

        $quantities = [$binA->id => 2, $binB->id => 8];
        $service->record($count, $fixture['admin'], $count->items->map(fn ($item) => ['id' => $item->id, 'counted_quantity' => $quantities[$item->stock_location_id]])->all());
        $service->submit($count, $fixture['admin']);
        $service->approve($count, $fixture['admin']);
        $service->post($count, $fixture['admin']);

        $this->assertDatabaseHas('stock_levels', ['stock_location_id' => $binA->id, 'quantity_on_hand' => 2]);
        $this->assertDatabaseHas('stock_levels', ['stock_location_id' => $binB->id, 'quantity_on_hand' => 8]);
        $this->assertSame(2, StockMovement::query()->where('reference_id', $count->id)->where('movement_type', 'physical_count')->count());
    }

    public function test_stock_lookup_and_reports_are_location_and_tenant_scoped(): void
    {
        $fixture = $this->fixture();
        $this->stock($fixture, $fixture['store_a'], 7);
        $this->stock($fixture, $fixture['store_b'], 3);
        $outside = $this->fixture();
        $this->stock($outside, $outside['store_a'], 99);
        $service = app(InventoryStockViewService::class);

        $page = $service->availability($fixture['admin'], ['search' => $fixture['product']->sku]);
        $this->assertCount(1, $page->items());
        $this->assertSame('10.000', number_format((float) collect($page->items())->first()->stockLevels->sum('quantity_available'), 3, '.', ''));

        $rows = $service->reportRows($fixture['admin'], 'stock-by-location', []);
        $this->assertCount(2, $rows);
        $this->assertSame(10.0, $rows->sum(fn (array $row) => (float) $row['available']));
        $this->assertFalse($rows->contains(fn (array $row) => (float) $row['available'] === 99.0));
        $this->actingAs($fixture['admin'])
            ->get(route('inventory.products.show', $fixture['product']))
            ->assertOk()
            ->assertSee('Stock by location')
            ->assertSee($fixture['product']->sku);
    }

    public function test_transfer_product_search_reports_stock_for_the_selected_bin_only(): void
    {
        $fixture = $this->fixture();
        $bin = StockLocation::create([
            'company_id' => $fixture['company']->id,
            'warehouse_id' => $fixture['store_a']->id,
            'name' => 'Transfer Bin',
            'code' => 'TRANSFER-BIN',
            'type' => 'bin',
            'is_active' => true,
        ]);
        $this->stock($fixture, $fixture['store_a'], 2);
        StockLevel::create([
            'company_id' => $fixture['company']->id,
            'branch_id' => $fixture['branch_a']->id,
            'warehouse_id' => $fixture['store_a']->id,
            'stock_location_id' => $bin->id,
            'product_id' => $fixture['product']->id,
            'quantity_on_hand' => 7,
            'quantity_available' => 7,
        ]);

        $query = [
            'q' => $fixture['product']->sku,
            'source_warehouse_id' => $fixture['store_a']->id,
            'destination_warehouse_id' => $fixture['store_b']->id,
        ];
        $this->actingAs($fixture['admin'])
            ->getJson(route('inventory.transfers.products', $query))
            ->assertOk()
            ->assertJsonPath('stock.'.$fixture['store_a']->id.'-'.$fixture['product']->id, 2);

        $this->getJson(route('inventory.transfers.products', $query + ['source_stock_location_id' => $bin->id]))
            ->assertOk()
            ->assertJsonPath('stock.'.$fixture['store_a']->id.'-'.$fixture['product']->id, 7);
    }

    public function test_pos_register_deducts_only_its_assigned_warehouse(): void
    {
        $fixture = $this->fixture();
        $source = $this->stock($fixture, $fixture['store_a'], 5);
        $other = $this->stock($fixture, $fixture['store_b'], 8);
        $registers = app(PosRegisterService::class);
        $register = $registers->create($fixture['admin'], [
            'branch_id' => $fixture['branch_a']->id,
            'warehouse_id' => $fixture['store_a']->id,
            'code' => 'A-COUNTER',
            'name' => 'Store A Counter',
            'receipt_prefix' => 'A',
        ]);
        $registers->open($register, $fixture['admin'], 0);

        $this->actingAs($fixture['admin'])->post('/pos/checkout', [
            'branch_id' => $fixture['branch_a']->id,
            'register_id' => $register->id,
            'items' => [['product_id' => $fixture['product']->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 200]],
        ])->assertRedirect();

        $this->assertSame('3.000', $source->refresh()->quantity_available);
        $this->assertSame('8.000', $other->refresh()->quantity_available);
        $this->assertDatabaseHas('stock_movements', ['warehouse_id' => $fixture['store_a']->id, 'movement_type' => 'sale', 'quantity' => 2]);
    }

    public function test_transfer_product_search_is_bounded_and_location_authorized(): void
    {
        $fixture = $this->fixture();
        $this->stock($fixture, $fixture['store_a'], 4);

        $this->actingAs($fixture['admin'])->getJson('/inventory/transfers/product-search?'.http_build_query([
            'q' => $fixture['product']->sku,
            'source_warehouse_id' => $fixture['store_a']->id,
            'destination_warehouse_id' => $fixture['store_b']->id,
        ]))->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $fixture['product']->id)
            ->assertJsonPath('stock.'.$fixture['store_a']->id.'-'.$fixture['product']->id, 4);

        $outside = $this->fixture();
        $response = $this->actingAs($fixture['admin'])->getJson('/inventory/transfers/product-search?'.http_build_query([
            'q' => $fixture['product']->sku,
            'source_warehouse_id' => $outside['store_a']->id,
            'destination_warehouse_id' => $fixture['store_b']->id,
        ]));
        $this->assertSame(403, $response->status());
    }

    public function test_manual_missing_package_discrepancy_does_not_mutate_stock_until_resolution(): void
    {
        $fixture = $this->fixture();
        $source = $this->stock($fixture, $fixture['store_a'], 5);
        $service = app(StockTransferService::class);
        $transfer = $this->readyTransfer($fixture, $service, 3);
        $service->dispatch($transfer, $fixture['admin']);
        $item = $transfer->refresh()->items->first();

        $discrepancy = $service->reportDiscrepancy($transfer, $item, $fixture['admin'], [
            'type' => 'missing_package',
            'reason' => 'One sealed carton is missing.',
            'expected_quantity' => 3,
            'actual_quantity' => 2,
            'discrepancy_quantity' => 1,
        ]);

        $this->assertSame('2.000', $source->refresh()->quantity_available);
        $this->assertSame('3.000', $item->refresh()->in_transit_quantity);
        $this->assertSame(0, StockLevel::query()->where('warehouse_id', $fixture['store_b']->id)->count());
        $service->resolveDiscrepancy($discrepancy, $fixture['admin'], 'restock_source', 'Package returned to dispatch desk.');
        $this->assertSame('3.000', $source->refresh()->quantity_available);
        $this->assertSame('2.000', $item->refresh()->in_transit_quantity);
    }

    public function test_destination_manager_can_report_and_resolve_destination_damage(): void
    {
        $fixture = $this->fixture();
        $destinationManager = User::factory()->for($fixture['company'])->create(['branch_id' => $fixture['branch_b']->id, 'role' => UserRole::Manager]);
        $this->stock($fixture, $fixture['store_a'], 3);
        $service = app(StockTransferService::class);
        $transfer = $this->readyTransfer($fixture, $service, 2);
        $service->dispatch($transfer, $fixture['admin']);
        $item = $transfer->refresh()->items->first();

        $discrepancy = $service->reportDiscrepancy($transfer, $item, $destinationManager, [
            'type' => 'damaged_in_transit',
            'reason' => 'Outer carton crushed at delivery.',
            'discrepancy_quantity' => 1,
        ]);
        $service->resolveDiscrepancy($discrepancy, $destinationManager, 'add_destination_damaged', 'Moved to the damaged-stock area.');

        $this->assertSame('1.000', $this->level($fixture, $fixture['store_b'])->quantity_damaged);
        $this->assertSame('1.000', $item->refresh()->in_transit_quantity);
        $this->assertDatabaseHas('inventory_transfer_discrepancies', [
            'id' => $discrepancy->id,
            'status' => 'resolved',
            'resolution' => 'add_destination_damaged',
            'resolved_by' => $destinationManager->id,
        ]);
    }

    public function test_inventory_report_ui_and_csv_share_minor_unit_values_and_escape_formulas(): void
    {
        $fixture = $this->fixture(['name' => ' =INJECT']);
        $this->stock($fixture, $fixture['store_a'], 7);
        $rows = app(InventoryStockViewService::class)->reportRows($fixture['admin'], 'stock-valuation', ['warehouse_id' => $fixture['store_a']->id]);

        $this->assertCount(1, $rows);
        $this->assertSame(42000, $rows->first()['stock_value_minor']);
        $this->assertSame('420.00', $rows->first()['stock_value']);

        $csv = $this->actingAs($fixture['admin'])->get('/inventory/reports/stock-valuation/export?warehouse_id='.$fixture['store_a']->id)->assertOk();
        $this->assertStringContainsString("' =INJECT", $csv->streamedContent());
        $this->assertStringContainsString('42000', $csv->streamedContent());
    }

    public function test_manager_location_access_does_not_include_other_stores_or_central_warehouses(): void
    {
        $fixture = $this->fixture();
        $manager = User::factory()->for($fixture['company'])->create(['branch_id' => $fixture['branch_a']->id, 'role' => UserRole::Manager]);
        $warehouses = app(InventoryLocationAccessService::class)->accessibleWarehouses($manager);

        $this->assertTrue($warehouses->contains('id', $fixture['store_a']->id));
        $this->assertFalse($warehouses->contains('id', $fixture['store_b']->id));
        $this->assertFalse($warehouses->contains('id', $fixture['central_a']->id));
    }

    public function test_stock_mutations_derive_branch_from_an_authorized_warehouse(): void
    {
        $fixture = $this->fixture();
        $manager = User::factory()->for($fixture['company'])->create(['branch_id' => $fixture['branch_a']->id, 'role' => UserRole::Manager]);
        $service = app(StockService::class);

        try {
            $service->recordOpeningStock($manager, [
                'warehouse_id' => $fixture['store_b']->id,
                'product_id' => $fixture['product']->id,
                'quantity' => 4,
            ]);
            $this->fail('A manager posted opening stock to an unauthorized outlet.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('warehouse_id', $exception->errors());
        }

        $movement = $service->recordOpeningStock($fixture['admin'], [
            'warehouse_id' => $fixture['central_a']->id,
            'product_id' => $fixture['product']->id,
            'quantity' => 4,
        ]);

        $this->assertNull($movement->branch_id);
        $this->assertSame($fixture['central_a']->id, $movement->warehouse_id);
        $this->assertDatabaseHas('stock_levels', [
            'company_id' => $fixture['company']->id,
            'branch_id' => null,
            'warehouse_id' => $fixture['central_a']->id,
            'product_id' => $fixture['product']->id,
            'quantity_on_hand' => 4,
        ]);
    }

    public function test_in_transit_serials_remain_scoped_to_authorized_transfer_locations(): void
    {
        $fixture = $this->fixture(['track_serials' => true]);
        $manager = User::factory()->for($fixture['company'])->create(['branch_id' => $fixture['branch_a']->id, 'role' => UserRole::Manager]);
        $this->stock($fixture, $fixture['store_b'], 1);
        $serial = InventorySerialNumber::create([
            'company_id' => $fixture['company']->id,
            'product_id' => $fixture['product']->id,
            'warehouse_id' => $fixture['store_b']->id,
            'serial_number' => 'PRIVATE-TRANSIT-SERIAL',
            'status' => 'available',
        ]);
        $service = app(StockTransferService::class);
        $transfer = $service->create($fixture['admin'], [
            'source_warehouse_id' => $fixture['store_b']->id,
            'destination_warehouse_id' => $fixture['central_a']->id,
            'items' => [['product_id' => $fixture['product']->id, 'quantity' => 1, 'serial_ids' => [$serial->id]]],
        ]);
        $service->submit($transfer, $fixture['admin']);
        $item = $transfer->refresh()->items->first();
        $service->approve($transfer, $fixture['admin'], [['id' => $item->id, 'approved_quantity' => 1]]);
        $service->pack($transfer, $fixture['admin'], [['id' => $item->id, 'packed_quantity' => 1]]);
        $service->dispatch($transfer, $fixture['admin']);

        $this->assertSame('in_transit', $serial->refresh()->status);
        $this->assertCount(0, app(InventoryStockViewService::class)->reportRows($manager, 'serials', []));

        $this->expectException(ValidationException::class);
        app(InventoryTraceabilityService::class)->updateSerial($manager, $serial, ['status' => 'sold']);
    }

    public function test_barcode_labels_are_machine_rendered_with_optional_batch_and_expiry(): void
    {
        $fixture = $this->fixture(['track_batches' => true, 'track_expiry' => true, 'barcode' => '890123456789']);
        $this->stock($fixture, $fixture['store_a'], 2);
        $inventoryBatch = InventoryBatch::create([
            'company_id' => $fixture['company']->id,
            'product_id' => $fixture['product']->id,
            'warehouse_id' => $fixture['store_a']->id,
            'batch_number' => 'LABEL-BATCH',
            'expires_at' => today()->addMonth(),
            'quantity_on_hand' => 2,
            'quantity_available' => 2,
            'status' => 'active',
        ]);
        $template = BarcodeLabelTemplate::create([
            'company_id' => $fixture['company']->id,
            'name' => 'Batch Label',
            'label_width_mm' => 50,
            'label_height_mm' => 25,
            'columns' => 2,
            'barcode_type' => 'EAN13',
            'font_size' => 9,
            'show_product_name' => true,
            'show_sku' => true,
            'show_barcode_text' => true,
            'show_price' => true,
            'show_batch' => true,
            'show_expiry' => true,
            'is_active' => true,
        ]);
        $printBatch = app(BarcodeService::class)->createPrintBatch($fixture['admin'], [
            'template_id' => $template->id,
            'items' => [['product_id' => $fixture['product']->id, 'inventory_batch_id' => $inventoryBatch->id, 'quantity' => 2]],
        ]);

        $this->assertStringContainsString('<svg', app(BarcodeRenderer::class)->svg($fixture['product']->barcode, 'EAN13'));
        $this->actingAs($fixture['admin'])->get('/inventory/barcode-batches/'.$printBatch->id)->assertOk()->assertSee('LABEL-BATCH')->assertSee('scanner-ready labels');
        $this->assertDatabaseHas('barcode_print_batch_items', ['print_batch_id' => $printBatch->id, 'inventory_batch_id' => $inventoryBatch->id, 'quantity' => 2]);
    }

    /** @param array<string, mixed> $productOverrides @return array<string, mixed> */
    private function fixture(array $productOverrides = []): array
    {
        $company = Company::factory()->create();
        $branchA = Branch::factory()->for($company)->create(['code' => 'STORE-A', 'is_active' => true]);
        $branchB = Branch::factory()->for($company)->create(['code' => 'STORE-B', 'is_active' => true]);
        $admin = User::factory()->for($company)->create(['branch_id' => $branchA->id, 'role' => UserRole::Administrator]);
        $unit = InventoryUnit::create(['company_id' => $company->id, 'name' => 'Piece', 'short_code' => 'PCS', 'type' => 'quantity', 'is_active' => true]);
        $category = InventoryCategory::create(['company_id' => $company->id, 'name' => 'Phase R', 'slug' => 'phase-r', 'is_active' => true]);
        $product = Product::create($productOverrides + [
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Phase R Product',
            'slug' => 'phase-r-product',
            'sku' => 'PHASE-R-001',
            'selling_price' => 100,
            'cost_price' => 60,
            'track_inventory' => true,
            'allow_negative_stock' => false,
            'status' => 'active',
            'is_active' => true,
        ]);
        $storeA = $this->warehouse($company, $branchA, 'Store A Stock', 'STORE-A-STOCK', 'store');
        $storeB = $this->warehouse($company, $branchB, 'Store B Stock', 'STORE-B-STOCK', 'store');
        $centralA = $this->warehouse($company, null, 'Central Warehouse', 'CENTRAL-A', 'warehouse');
        $centralB = $this->warehouse($company, null, 'Overflow Warehouse', 'CENTRAL-B', 'warehouse');

        return [
            'company' => $company,
            'admin' => $admin,
            'product' => $product,
            'branch_a' => $branchA,
            'branch_b' => $branchB,
            'store_a' => $storeA,
            'store_b' => $storeB,
            'central_a' => $centralA,
            'central_b' => $centralB,
        ];
    }

    private function warehouse(Company $company, ?Branch $branch, string $name, string $code, string $type): Warehouse
    {
        return Warehouse::create(['company_id' => $company->id, 'branch_id' => $branch?->id, 'name' => $name, 'code' => $code, 'type' => $type, 'country' => 'India', 'is_active' => true]);
    }

    /** @param array<string, mixed> $fixture */
    private function stock(array $fixture, Warehouse $warehouse, float $quantity): StockLevel
    {
        return StockLevel::create([
            'company_id' => $fixture['company']->id,
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $fixture['product']->id,
            'quantity_on_hand' => $quantity,
            'quantity_available' => $quantity,
            'quantity_reserved' => 0,
            'quantity_damaged' => 0,
        ]);
    }

    /** @param array<string, mixed> $fixture */
    private function level(array $fixture, Warehouse $warehouse): StockLevel
    {
        return StockLevel::query()->where('company_id', $fixture['company']->id)->where('warehouse_id', $warehouse->id)->where('product_id', $fixture['product']->id)->sole();
    }

    /** @param array<string, mixed> $fixture */
    private function readyTransfer(array $fixture, StockTransferService $service, float $quantity): StockTransfer
    {
        $transfer = $service->create($fixture['admin'], [
            'source_warehouse_id' => $fixture['store_a']->id,
            'destination_warehouse_id' => $fixture['store_b']->id,
            'items' => [['product_id' => $fixture['product']->id, 'quantity' => $quantity]],
        ]);
        $service->submit($transfer, $fixture['admin']);
        $item = $transfer->refresh()->items->first();
        $service->approve($transfer, $fixture['admin'], [['id' => $item->id, 'approved_quantity' => $quantity]]);
        $service->pack($transfer, $fixture['admin'], [['id' => $item->id, 'packed_quantity' => $quantity]]);

        return $transfer->refresh();
    }
}
