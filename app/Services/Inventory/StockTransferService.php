<?php

namespace App\Services\Inventory;

use App\Models\Branch;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(private readonly AuditLogger $audit, private readonly OutletAccessService $outlets) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): StockTransfer
    {
        return DB::transaction(function () use ($user, $data): StockTransfer {
            $source = $this->outlet($data['source_branch_id'], $user);
            $destination = $this->outlet($data['destination_branch_id'], $user);
            $this->assertPair($source, $destination, $user);
            $sourceWarehouse = $this->warehouse($source, $user);
            $destinationWarehouse = $this->warehouse($destination, $user);
            $number = 'TRF-'.now()->format('Y').'-'.str_pad((string) (StockTransfer::query()->where('company_id', $user->company_id)->lockForUpdate()->count() + 1), 5, '0', STR_PAD_LEFT);
            $transfer = StockTransfer::create(['company_id' => $user->company_id, 'source_branch_id' => $source->id, 'destination_branch_id' => $destination->id, 'source_warehouse_id' => $sourceWarehouse->id, 'destination_warehouse_id' => $destinationWarehouse->id, 'transfer_number' => $number, 'status' => StockTransfer::DRAFT, 'notes' => $data['notes'] ?? null, 'requested_by' => $user->id]);
            foreach ($data['items'] as $line) {
                Product::query()->where('company_id', $user->company_id)->findOrFail($line['product_id']);
                $transfer->items()->create(['product_id' => $line['product_id'], 'requested_quantity' => $line['quantity']]);
            }
            $this->audit->record('outlet.transfer.created', $transfer, 'Stock transfer created.', ['company_id' => $user->company_id, 'transfer_id' => $transfer->id]);
            return $transfer->load('items.product');
        });
    }

    public function dispatch(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = $this->locked($transfer->id, $user);
            if ($transfer->status === StockTransfer::IN_TRANSIT) return $transfer;
            if ($transfer->status !== StockTransfer::DRAFT) throw ValidationException::withMessages(['transfer' => 'Only draft transfers can be dispatched.']);
            $source = $this->outlet($transfer->source_branch_id, $user); $destination = $this->outlet($transfer->destination_branch_id, $user); $this->assertPair($source, $destination, $user);
            foreach ($transfer->items()->with('product')->get() as $item) {
                $quantity = (float) $item->requested_quantity;
                $level = $this->lockedLevel($user->company_id, $transfer->source_warehouse_id, $item->product_id, $source->id);
                if (! $item->product->allow_negative_stock && (float) $level->quantity_available < $quantity) throw ValidationException::withMessages(['items' => "Insufficient available stock for {$item->product->name}."]);
                $this->move($level, $source->id, $item->product, -$quantity, 'transfer_dispatch', 'outbound', $transfer, $user);
                $item->update(['dispatched_quantity' => $quantity]);
            }
            $transfer->update(['status' => StockTransfer::IN_TRANSIT, 'submitted_at' => now(), 'dispatched_at' => now(), 'dispatched_by' => $user->id]);
            $this->audit->record('outlet.transfer.dispatched', $transfer, 'Stock transfer dispatched.', ['company_id' => $user->company_id, 'transfer_id' => $transfer->id]);
            return $transfer->refresh()->load('items.product');
        });
    }

    public function receive(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = $this->locked($transfer->id, $user);
            if ($transfer->status === StockTransfer::RECEIVED) return $transfer;
            if ($transfer->status !== StockTransfer::IN_TRANSIT) throw ValidationException::withMessages(['transfer' => 'Only an in-transit transfer can be received.']);
            $destination = $this->outlet($transfer->destination_branch_id, $user);
            if (! $this->outlets->canAccess($user, $destination)) throw ValidationException::withMessages(['outlet' => 'You are not assigned to the destination outlet.']);
            foreach ($transfer->items()->with('product')->get() as $item) {
                $quantity = (float) $item->dispatched_quantity;
                if ($quantity <= 0 || (float) $item->received_quantity > 0) throw ValidationException::withMessages(['items' => 'This transfer line cannot be received again.']);
                $level = $this->lockedLevel($user->company_id, $transfer->destination_warehouse_id, $item->product_id, $destination->id);
                $this->move($level, $destination->id, $item->product, $quantity, 'transfer_receipt', 'inbound', $transfer, $user);
                $item->update(['received_quantity' => $quantity]);
            }
            $transfer->update(['status' => StockTransfer::RECEIVED, 'received_at' => now(), 'received_by' => $user->id]);
            $this->audit->record('outlet.transfer.received', $transfer, 'Stock transfer received.', ['company_id' => $user->company_id, 'transfer_id' => $transfer->id]);
            return $transfer->refresh()->load('items.product');
        });
    }

    private function locked(int $transferId, User $user): StockTransfer { return StockTransfer::query()->where('company_id', $user->company_id)->with('items.product')->lockForUpdate()->findOrFail($transferId); }
    private function outlet(int $id, User $user): Branch { return Branch::query()->where('company_id', $user->company_id)->where('is_active', true)->findOrFail($id); }
    private function warehouse(Branch $outlet, User $user): Warehouse { return Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $outlet->id)->where('is_active', true)->orderByDesc('is_primary')->firstOrFail(); }
    private function assertPair(Branch $source, Branch $destination, User $user): void { if ($source->id === $destination->id) throw ValidationException::withMessages(['destination_branch_id' => 'Choose a different destination outlet.']); if (! $this->outlets->canAccess($user, $source) || ! $this->outlets->canAccess($user, $destination)) throw ValidationException::withMessages(['outlet' => 'You are not assigned to both transfer outlets.']); }
    private function lockedLevel(int $companyId, int $warehouseId, int $productId, int $branchId): StockLevel { $level = StockLevel::query()->firstOrCreate(['company_id' => $companyId, 'warehouse_id' => $warehouseId, 'stock_location_id' => null, 'product_id' => $productId], ['branch_id' => $branchId, 'quantity_on_hand' => 0, 'quantity_available' => 0, 'quantity_reserved' => 0]); return StockLevel::query()->lockForUpdate()->findOrFail($level->id); }
    private function move(StockLevel $level, int $branchId, Product $product, float $quantity, string $type, string $direction, StockTransfer $transfer, User $user): void { $before = (float) $level->quantity_on_hand; $after = $before + $quantity; $level->update(['branch_id' => $branchId, 'quantity_on_hand' => $after, 'quantity_available' => $after - (float) $level->quantity_reserved, 'last_stock_movement_at' => now()]); StockMovement::create(['company_id' => $user->company_id, 'branch_id' => $branchId, 'warehouse_id' => $level->warehouse_id, 'product_id' => $product->id, 'movement_type' => $type, 'direction' => $direction, 'quantity' => abs($quantity), 'quantity_before' => $before, 'quantity_after' => $after, 'unit_cost' => $product->cost_price, 'reference_type' => StockTransfer::class, 'reference_id' => $transfer->id, 'reason' => 'Outlet transfer '.$transfer->transfer_number, 'created_by' => $user->id, 'occurred_at' => now()]); }
}
