<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Services\Inventory\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_and_idempotent_receipt_move_stock_only_at_operational_transitions(): void
    {
        [$manager, $source] = $this->userWithMainOutlet();
        $destination = Branch::factory()->for($manager->company)->create(['code' => 'CBE-02']);
        BranchUserAssignment::create(['company_id' => $manager->company_id, 'branch_id' => $destination->id, 'user_id' => $manager->id, 'is_active' => true, 'assigned_by' => $manager->id]);
        $sourceWarehouse = $this->warehouse($manager, $source, 'WH-MAIN');
        $destinationWarehouse = $this->warehouse($manager, $destination, 'WH-CBE');
        $product = $this->product($manager);
        StockLevel::create(['company_id' => $manager->company_id, 'branch_id' => $source->id, 'warehouse_id' => $sourceWarehouse->id, 'product_id' => $product->id, 'quantity_on_hand' => 10, 'quantity_available' => 10]);
        $service = app(StockTransferService::class);

        $transfer = $service->create($manager, ['source_branch_id' => $source->id, 'destination_branch_id' => $destination->id, 'items' => [['product_id' => $product->id, 'quantity' => 4]]]);
        $this->assertSame(StockTransfer::DRAFT, $transfer->status);
        $this->assertSame('10.000', StockLevel::query()->where('warehouse_id', $sourceWarehouse->id)->sole()->quantity_on_hand);

        $service->dispatch($transfer, $manager);
        $this->assertSame('6.000', StockLevel::query()->where('warehouse_id', $sourceWarehouse->id)->sole()->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', ['reference_id' => $transfer->id, 'movement_type' => 'transfer_dispatch']);

        $service->receive($transfer, $manager);
        $this->assertSame('4.000', StockLevel::query()->where('warehouse_id', $destinationWarehouse->id)->sole()->quantity_on_hand);
        $service->receive($transfer, $manager);
        $this->assertSame(1, StockLevel::query()->where('warehouse_id', $destinationWarehouse->id)->count());
        $this->assertDatabaseHas('stock_transfers', ['id' => $transfer->id, 'status' => StockTransfer::RECEIVED]);
    }

    public function test_rejects_same_outlet_and_cross_tenant_access(): void
    {
        [$manager, $source] = $this->userWithMainOutlet();
        $this->warehouse($manager, $source, 'WH-MAIN');
        $service = app(StockTransferService::class);

        try {
            $service->create($manager, ['source_branch_id' => $source->id, 'destination_branch_id' => $source->id, 'items' => [['product_id' => 1, 'quantity' => 1]]]);
            $this->fail('A same-outlet transfer was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('destination_branch_id', $exception->errors());
        }

        [$otherManager, $otherOutlet] = $this->userWithMainOutlet();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->create($otherManager, ['source_branch_id' => $source->id, 'destination_branch_id' => $otherOutlet->id, 'items' => [['product_id' => 1, 'quantity' => 1]]]);
    }

    /** @return array{User, Branch} */
    private function userWithMainOutlet(): array
    {
        $company = Company::factory()->create();
        $main = Branch::factory()->for($company)->create(['code' => 'MAIN', 'is_primary' => true]);
        $user = User::factory()->for($company)->create(['branch_id' => $main->id, 'role' => UserRole::Manager]);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $main->id, 'user_id' => $user->id, 'is_default' => true, 'is_active' => true, 'assigned_by' => $user->id]);

        return [$user, $main];
    }

    private function product(User $user): Product
    {
        $unit = InventoryUnit::create(['company_id' => $user->company_id, 'name' => 'Piece', 'short_code' => 'PCS', 'is_active' => true]);

        return Product::create(['company_id' => $user->company_id, 'unit_id' => $unit->id, 'name' => 'Outlet Product', 'slug' => 'outlet-product', 'sku' => 'OUTLET-001', 'selling_price' => 100, 'is_active' => true]);
    }

    private function warehouse(User $user, Branch $outlet, string $code): Warehouse
    {
        return Warehouse::create(['company_id' => $user->company_id, 'branch_id' => $outlet->id, 'name' => $outlet->name.' Warehouse', 'code' => $code, 'type' => 'store', 'is_active' => true]);
    }
}
