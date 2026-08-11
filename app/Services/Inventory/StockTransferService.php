<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventorySerialNumber;
use App\Models\Inventory\InventoryTransferDiscrepancy;
use App\Models\Inventory\InventoryTransferReceipt;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferItem;
use App\Models\Inventory\Warehouse;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InventoryLocationAccessService $locations,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): StockTransfer
    {
        return DB::transaction(function () use ($user, $data): StockTransfer {
            if (! empty($data['idempotency_key'])) {
                $existing = StockTransfer::query()
                    ->where('company_id', $user->company_id)
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();
                if ($existing) {
                    return $existing->load('items.product');
                }
            }

            $source = isset($data['source_warehouse_id'])
                ? $this->locations->authorize($user, (int) $data['source_warehouse_id'])
                : $this->warehouseForOutlet($user, (int) $data['source_branch_id']);
            $destination = isset($data['destination_warehouse_id'])
                ? $this->locations->authorize($user, (int) $data['destination_warehouse_id'])
                : $this->warehouseForOutlet($user, (int) $data['destination_branch_id']);
            if ($source->is($destination)) {
                $key = isset($data['destination_warehouse_id']) ? 'destination_warehouse_id' : 'destination_branch_id';
                throw ValidationException::withMessages([$key => 'Choose a different destination.']);
            }

            $sourceLocationId = $this->locationId($user, $source, $data['source_stock_location_id'] ?? null, 'source_stock_location_id');
            $destinationLocationId = $this->locationId($user, $destination, $data['destination_stock_location_id'] ?? null, 'destination_stock_location_id');
            $transfer = StockTransfer::create([
                'company_id' => $user->company_id,
                'source_branch_id' => $source->branch_id,
                'destination_branch_id' => $destination->branch_id,
                'source_warehouse_id' => $source->id,
                'destination_warehouse_id' => $destination->id,
                'source_stock_location_id' => $sourceLocationId,
                'destination_stock_location_id' => $destinationLocationId,
                'transfer_number' => $this->nextNumber(),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'status' => StockTransfer::DRAFT,
                'notes' => $data['notes'] ?? null,
                'expected_arrival_at' => $data['expected_arrival_at'] ?? null,
                'requested_by' => $user->id,
            ]);

            foreach ($data['items'] as $line) {
                $product = Product::query()->with('unit')->where('company_id', $user->company_id)->where('is_active', true)->findOrFail((int) $line['product_id']);
                $quantity = $this->positive($line['quantity'], 'Transfer quantities must be greater than zero.');
                $item = $transfer->items()->create([
                    'product_id' => $product->id,
                    'source_stock_location_id' => $line['source_stock_location_id'] ?? $sourceLocationId,
                    'destination_stock_location_id' => $line['destination_stock_location_id'] ?? $destinationLocationId,
                    'inventory_batch_id' => $line['inventory_batch_id'] ?? null,
                    'requested_quantity' => $quantity,
                    'unit_snapshot' => $product->unit?->short_code,
                    'notes' => $line['notes'] ?? null,
                ]);
                if ($product->track_batches) {
                    if (empty($line['inventory_batch_id'])) {
                        throw ValidationException::withMessages(['items' => "Choose a source batch for {$product->name}."]);
                    }
                    $batch = InventoryBatch::query()->where('company_id', $user->company_id)->where('product_id', $product->id)->where('warehouse_id', $source->id)->where('status', 'active')->findOrFail((int) $line['inventory_batch_id']);
                    if ((float) $batch->quantity_available < $quantity) {
                        throw ValidationException::withMessages(['items' => "Insufficient available batch stock for {$product->name}."]);
                    }
                }
                if ($product->track_serials) {
                    $serialIds = collect($line['serial_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
                    if (abs($quantity - round($quantity)) > 0.0005 || $serialIds->count() !== (int) round($quantity)) {
                        throw ValidationException::withMessages(['items' => "Select one available serial number for each unit of {$product->name}."]);
                    }
                    $serials = InventorySerialNumber::query()->where('company_id', $user->company_id)->where('product_id', $product->id)->where('warehouse_id', $source->id)->where('status', 'available')->whereIn('id', $serialIds)->get();
                    if ($serials->count() !== $serialIds->count()) {
                        throw ValidationException::withMessages(['items' => "One or more serial numbers for {$product->name} are unavailable at the source."]);
                    }
                    $item->serialNumbers()->attach($serialIds->all(), ['status' => 'reserved']);
                    InventorySerialNumber::query()->whereIn('id', $serialIds)->update(['status' => 'reserved']);
                }
            }

            $this->audit('inventory.transfer.created', $transfer, $user, 'Stock transfer draft created.');

            return $transfer->load($this->relations());
        });
    }

    public function submit(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = $this->locked($transfer->id, $user);
            if ($transfer->status !== StockTransfer::DRAFT) {
                return $transfer;
            }

            $this->authorizePair($transfer, $user);
            $approvalRequired = $this->setting($user->company_id, 'require_transfer_approval', true);
            $status = $approvalRequired ? StockTransfer::PENDING_APPROVAL : StockTransfer::APPROVED;
            if (! $approvalRequired) {
                $transfer->items()->update(['approved_quantity' => DB::raw('requested_quantity')]);
            }
            $transfer->update([
                'status' => $status,
                'submitted_at' => now(),
                'requested_at' => now(),
                'approved_by' => $approvalRequired ? null : $user->id,
                'approved_at' => $approvalRequired ? null : now(),
            ]);
            $this->audit('inventory.transfer.submitted', $transfer, $user, $approvalRequired ? 'Stock transfer submitted for approval.' : 'Stock transfer submitted and approved by company settings.');

            return $transfer->refresh()->load($this->relations());
        });
    }

    /** @param array<int, array<string, mixed>> $items */
    public function approve(StockTransfer $transfer, User $user, array $items = [], ?string $notes = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $items, $notes): StockTransfer {
            $transfer = $this->locked($transfer->id, $user);
            if ($transfer->status === StockTransfer::APPROVED) {
                return $transfer;
            }
            if (! in_array($transfer->status, [StockTransfer::REQUESTED, StockTransfer::PENDING_APPROVAL], true)) {
                throw ValidationException::withMessages(['transfer' => 'Only a transfer awaiting approval can be approved.']);
            }
            $this->authorizePair($transfer, $user);
            $input = collect($items)->keyBy(fn (array $item) => (int) $item['id']);
            $quantityChanged = false;
            foreach ($transfer->items as $item) {
                $approved = isset($input[$item->id]) ? (float) $input[$item->id]['approved_quantity'] : (float) $item->requested_quantity;
                if ($approved < 0 || $approved > (float) $item->requested_quantity) {
                    throw ValidationException::withMessages(['items' => "Approved quantity for {$item->product->name} must be between zero and the requested quantity."]);
                }
                $quantityChanged = $quantityChanged || abs($approved - (float) $item->requested_quantity) > 0.0005;
                $item->update(['approved_quantity' => $approved]);
            }
            if ($quantityChanged && blank($notes)) {
                throw ValidationException::withMessages(['notes' => 'Explain why an approved quantity was changed.']);
            }
            if ($transfer->items()->sum('approved_quantity') <= 0) {
                throw ValidationException::withMessages(['items' => 'Approve at least one item or reject the transfer.']);
            }
            $transfer->update(['status' => StockTransfer::APPROVED, 'approved_by' => $user->id, 'approved_at' => now(), 'notes' => $notes ?: $transfer->notes]);
            $this->audit('inventory.transfer.approved', $transfer, $user, 'Stock transfer approved.');

            return $transfer->refresh()->load($this->relations());
        });
    }

    public function reject(StockTransfer $transfer, User $user, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $reason): StockTransfer {
            $transfer = $this->locked($transfer->id, $user);
            if (! in_array($transfer->status, [StockTransfer::REQUESTED, StockTransfer::PENDING_APPROVAL], true)) {
                throw ValidationException::withMessages(['transfer' => 'Only a transfer awaiting approval can be rejected.']);
            }
            $this->authorizePair($transfer, $user);
            $this->releaseReservedSerials($transfer);
            $transfer->update(['status' => StockTransfer::REJECTED, 'rejected_by' => $user->id, 'rejected_at' => now(), 'rejection_reason' => $reason]);
            $this->audit('inventory.transfer.rejected', $transfer, $user, 'Stock transfer rejected.');

            return $transfer->refresh();
        });
    }

    /** @param array<int, array<string, mixed>> $items */
    public function pack(StockTransfer $transfer, User $user, array $items = []): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $items): StockTransfer {
            $transfer = $this->locked($transfer->id, $user);
            if (! in_array($transfer->status, [StockTransfer::APPROVED, StockTransfer::PACKING], true)) {
                throw ValidationException::withMessages(['transfer' => 'Only an approved transfer can be packed.']);
            }
            $this->locations->authorize($user, $transfer->source_warehouse_id);
            $input = collect($items)->keyBy(fn (array $item) => (int) $item['id']);
            foreach ($transfer->items as $item) {
                $packed = isset($input[$item->id]) ? (float) $input[$item->id]['packed_quantity'] : (float) $item->approved_quantity;
                if ($packed < 0 || $packed > (float) $item->approved_quantity) {
                    throw ValidationException::withMessages(['items' => "Packed quantity for {$item->product->name} cannot exceed the approved quantity."]);
                }
                $available = (float) $this->lockedLevel($transfer, $item, true)->quantity_available;
                if (! $item->product->allow_negative_stock && $packed > $available) {
                    throw ValidationException::withMessages(['items' => "Only {$available} {$item->unit_snapshot} of {$item->product->name} is available at the source."]);
                }
                $item->update(['packed_quantity' => $packed]);
            }
            $transfer->update(['status' => StockTransfer::PACKING, 'packed_by' => $user->id, 'packed_at' => now()]);
            $this->audit('inventory.transfer.packed', $transfer, $user, 'Stock transfer packed.');

            return $transfer->refresh()->load($this->relations());
        });
    }

    public function dispatch(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = $this->locked($transfer->id, $user);
            if (in_array($transfer->status, [StockTransfer::DISPATCHED, StockTransfer::IN_TRANSIT, StockTransfer::PARTIALLY_RECEIVED, StockTransfer::DISCREPANCY, StockTransfer::RECEIVED], true)) {
                return $transfer;
            }
            if ($transfer->status === StockTransfer::DRAFT && ! $this->setting($user->company_id, 'require_transfer_approval', false)) {
                $transfer->items()->update(['approved_quantity' => DB::raw('requested_quantity'), 'packed_quantity' => DB::raw('requested_quantity')]);
                $transfer->update(['approved_by' => $user->id, 'approved_at' => now(), 'packed_by' => $user->id, 'packed_at' => now()]);
                $transfer->load('items.product');
            } elseif (! in_array($transfer->status, [StockTransfer::APPROVED, StockTransfer::PACKING], true)) {
                throw ValidationException::withMessages(['transfer' => 'This transfer is not ready to dispatch.']);
            }

            $this->locations->authorize($user, $transfer->source_warehouse_id);
            foreach ($transfer->items as $item) {
                $quantity = (float) ($item->packed_quantity > 0 ? $item->packed_quantity : $item->approved_quantity);
                if ($quantity <= 0) {
                    continue;
                }
                $level = $this->lockedLevel($transfer, $item, true);
                if (! $item->product->allow_negative_stock && (float) $level->quantity_available < $quantity) {
                    throw ValidationException::withMessages(['items' => "Insufficient available stock for {$item->product->name}."]);
                }
                $this->moveAvailable($level, -$quantity, 'transfer_dispatch', 'out', 'available', 'in_transit', $transfer, $item, $user);
                $this->moveBatch($transfer, $item, -$quantity, true);
                foreach ($item->serialNumbers as $serial) {
                    $serial->update(['status' => 'in_transit', 'warehouse_id' => null, 'stock_location_id' => null, 'reference_type' => StockTransfer::class, 'reference_id' => $transfer->id]);
                    $item->serialNumbers()->updateExistingPivot($serial->id, ['status' => 'dispatched']);
                }
                $item->update(['dispatched_quantity' => $quantity, 'in_transit_quantity' => $quantity]);
            }
            if ($transfer->items()->sum('dispatched_quantity') <= 0) {
                throw ValidationException::withMessages(['items' => 'Pack at least one item before dispatch.']);
            }
            $transfer->update(['status' => StockTransfer::IN_TRANSIT, 'dispatched_at' => now(), 'dispatched_by' => $user->id]);
            $this->audit('inventory.transfer.dispatched', $transfer, $user, 'Stock transfer dispatched into in-transit stock.');

            return $transfer->refresh()->load($this->relations());
        });
    }

    /** @param array<string, mixed> $data */
    public function receive(StockTransfer $transfer, User $user, array $data = []): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $data): StockTransfer {
            $transfer = $this->locked($transfer->id, $user);
            if ($transfer->status === StockTransfer::RECEIVED) {
                return $transfer;
            }
            if (! in_array($transfer->status, [StockTransfer::IN_TRANSIT, StockTransfer::PARTIALLY_RECEIVED, StockTransfer::DISCREPANCY], true)) {
                throw ValidationException::withMessages(['transfer' => 'Only dispatched stock can be received.']);
            }
            $this->locations->authorize($user, $transfer->destination_warehouse_id);
            if (! empty($data['idempotency_key'])) {
                $existing = InventoryTransferReceipt::query()->where('company_id', $user->company_id)->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $transfer->refresh()->load($this->relations());
                }
            }

            $input = collect($data['items'] ?? [])->keyBy(fn (array $item) => (int) $item['id']);
            $receipt = InventoryTransferReceipt::create([
                'company_id' => $user->company_id,
                'stock_transfer_id' => $transfer->id,
                'receipt_number' => 'RCV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'received_by' => $user->id,
                'received_at' => now(),
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $handled = 0.0;
            foreach ($transfer->items as $item) {
                $remaining = (float) $item->in_transit_quantity;
                $line = $input->get($item->id);
                $received = $line ? (float) ($line['received_quantity'] ?? 0) : ($input->isEmpty() ? $remaining : 0);
                $damaged = $line ? (float) ($line['damaged_quantity'] ?? 0) : 0;
                $short = $line ? (float) ($line['short_quantity'] ?? 0) : 0;
                if (min($received, $damaged, $short) < 0 || $received + $damaged > $remaining || $short > $remaining - $received - $damaged) {
                    throw ValidationException::withMessages(['items' => "Received quantities for {$item->product->name} exceed the quantity still in transit."]);
                }
                if ($received + $damaged + $short <= 0) {
                    continue;
                }

                $receipt->items()->create([
                    'stock_transfer_item_id' => $item->id,
                    'received_quantity' => $received,
                    'damaged_quantity' => $damaged,
                    'short_quantity' => $short,
                    'notes' => $line['notes'] ?? null,
                ]);
                $destination = $this->lockedLevel($transfer, $item, false);
                if ($received > 0) {
                    $this->moveAvailable($destination, $received, 'transfer_receipt', 'in', 'in_transit', 'available', $transfer, $item, $user);
                    $this->moveBatch($transfer, $item, $received, false);
                }
                if ($damaged > 0) {
                    $this->moveDamaged($destination, $damaged, $transfer, $item, $user);
                    $this->moveBatchToDamaged($transfer, $item, $damaged);
                    $this->discrepancy($transfer, $item, $receipt, 'damaged_in_transit', $remaining, $received, $damaged, $user, $line['notes'] ?? null);
                }
                if ($short > 0) {
                    $this->discrepancy($transfer, $item, $receipt, 'short_received', $remaining, $received + $damaged, $short, $user, $line['notes'] ?? null);
                }
                $item->update([
                    'received_quantity' => (float) $item->received_quantity + $received,
                    'damaged_quantity' => (float) $item->damaged_quantity + $damaged,
                    'short_quantity' => (float) $item->short_quantity + $short,
                    'in_transit_quantity' => $remaining - $received - $damaged,
                ]);
                if ($item->product->track_serials) {
                    $serialIds = collect($line['serial_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
                    $handledSerials = (int) round($received + $damaged);
                    if ($serialIds->isEmpty() && abs($remaining - ($received + $damaged)) < 0.0005) {
                        $serialIds = $item->serialNumbers->where('status', 'in_transit')->take($handledSerials)->pluck('id');
                    }
                    if ($serialIds->count() !== $handledSerials) {
                        throw ValidationException::withMessages(['items' => "Select each serial number being received for {$item->product->name}."]);
                    }
                    $serials = $item->serialNumbers->whereIn('id', $serialIds)->where('status', 'in_transit');
                    if ($serials->count() !== $serialIds->count()) {
                        throw ValidationException::withMessages(['items' => "A selected serial number for {$item->product->name} is not in transit."]);
                    }
                    $availableCount = (int) round($received);
                    foreach ($serials->values() as $index => $serial) {
                        $status = $index < $availableCount ? 'available' : 'damaged';
                        $serial->update(['status' => $status, 'warehouse_id' => $transfer->destination_warehouse_id, 'stock_location_id' => $item->destination_stock_location_id, 'reference_type' => StockTransfer::class, 'reference_id' => $transfer->id]);
                        $item->serialNumbers()->updateExistingPivot($serial->id, ['status' => $status === 'available' ? 'received' : 'damaged']);
                    }
                }
                $handled += $received + $damaged + $short;
            }
            if ($handled <= 0) {
                throw ValidationException::withMessages(['items' => 'Enter at least one received, damaged, or short quantity.']);
            }

            $this->refreshStatus($transfer, $user);
            $event = $transfer->status === StockTransfer::RECEIVED ? 'inventory.transfer.received' : 'inventory.transfer.partially_received';
            $this->audit($event, $transfer, $user, $transfer->status === StockTransfer::RECEIVED ? 'Stock transfer fully received.' : 'Stock transfer receipt recorded.');

            return $transfer->refresh()->load($this->relations());
        });
    }

    public function cancel(StockTransfer $transfer, User $user, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $reason): StockTransfer {
            $transfer = $this->locked($transfer->id, $user);
            if (! in_array($transfer->status, [StockTransfer::DRAFT, StockTransfer::REQUESTED, StockTransfer::PENDING_APPROVAL, StockTransfer::APPROVED, StockTransfer::PACKING], true)) {
                throw ValidationException::withMessages(['transfer' => 'Dispatched or completed transfers cannot be cancelled.']);
            }
            $this->authorizePair($transfer, $user);
            $this->releaseReservedSerials($transfer);
            $transfer->update(['status' => StockTransfer::CANCELLED, 'cancelled_by' => $user->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $this->audit('inventory.transfer.cancelled', $transfer, $user, 'Stock transfer cancelled.');

            return $transfer->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function reportDiscrepancy(StockTransfer $transfer, StockTransferItem $item, User $user, array $data): InventoryTransferDiscrepancy
    {
        return DB::transaction(function () use ($transfer, $item, $user, $data): InventoryTransferDiscrepancy {
            $transfer = $this->locked($transfer->id, $user);
            $this->locations->authorize($user, $transfer->destination_warehouse_id);
            if (! in_array($transfer->status, [StockTransfer::IN_TRANSIT, StockTransfer::PARTIALLY_RECEIVED, StockTransfer::DISCREPANCY], true)) {
                throw ValidationException::withMessages(['transfer' => 'Discrepancies can only be reported after dispatch and before completion.']);
            }
            $item = $transfer->items->firstWhere('id', $item->id);
            if (! $item) {
                throw ValidationException::withMessages(['item_id' => 'Choose a product from this transfer.']);
            }
            $quantity = $this->positive($data['discrepancy_quantity'], 'Discrepancy quantity must be greater than zero.');
            $record = InventoryTransferDiscrepancy::create([
                'company_id' => $transfer->company_id,
                'stock_transfer_id' => $transfer->id,
                'stock_transfer_item_id' => $item->id,
                'type' => $data['type'],
                'reason' => $data['reason'],
                'expected_quantity' => $data['expected_quantity'] ?? $item->in_transit_quantity,
                'actual_quantity' => $data['actual_quantity'] ?? 0,
                'discrepancy_quantity' => $quantity,
                'notes' => $data['notes'] ?? null,
                'reported_by' => $user->id,
            ]);
            $transfer->update(['status' => StockTransfer::DISCREPANCY]);
            $this->audit('inventory.transfer.discrepancy_recorded', $transfer, $user, 'Stock transfer discrepancy recorded.', ['type' => $record->type, 'quantity' => $quantity, 'product_id' => $item->product_id]);

            return $record->refresh();
        });
    }

    public function resolveDiscrepancy(InventoryTransferDiscrepancy $discrepancy, User $user, string $resolution, ?string $notes = null): InventoryTransferDiscrepancy
    {
        return DB::transaction(function () use ($discrepancy, $user, $resolution, $notes): InventoryTransferDiscrepancy {
            $discrepancy = InventoryTransferDiscrepancy::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($discrepancy->id);
            if ($discrepancy->status === 'resolved') {
                return $discrepancy;
            }
            $transfer = $this->locked($discrepancy->stock_transfer_id, $user);
            $item = $transfer->items->firstWhere('id', $discrepancy->stock_transfer_item_id);
            $quantity = (float) $discrepancy->discrepancy_quantity;

            if ($resolution === 'restock_source') {
                $this->locations->authorize($user, $transfer->source_warehouse_id);
            } elseif ($resolution === 'add_destination_damaged') {
                $this->locations->authorize($user, $transfer->destination_warehouse_id);
            } else {
                $this->authorizeEither($transfer, $user);
            }

            if (in_array($discrepancy->type, ['short_received', 'missing_package'], true)) {
                if ($quantity > (float) $item->in_transit_quantity) {
                    throw ValidationException::withMessages(['resolution' => 'The unresolved quantity is no longer in transit.']);
                }
                if ($resolution === 'restock_source') {
                    $source = $this->lockedLevel($transfer, $item, true);
                    $this->moveAvailable($source, $quantity, 'transfer_discrepancy_return', 'in', 'in_transit', 'available', $transfer, $item, $user);
                    $this->moveBatch($transfer, $item, $quantity, true);
                } elseif ($resolution === 'add_destination_damaged') {
                    $destination = $this->lockedLevel($transfer, $item, false);
                    $this->moveDamaged($destination, $quantity, $transfer, $item, $user);
                    $this->moveBatchToDamaged($transfer, $item, $quantity);
                } elseif (! in_array($resolution, ['confirm_loss', 'manager_adjustment'], true)) {
                    throw ValidationException::withMessages(['resolution' => 'Choose a valid discrepancy resolution.']);
                }
                $item->update(['in_transit_quantity' => (float) $item->in_transit_quantity - $quantity]);
            } elseif ($discrepancy->type === 'damaged_in_transit' && ! $discrepancy->inventory_transfer_receipt_id && $resolution === 'add_destination_damaged') {
                if ($quantity > (float) $item->in_transit_quantity) {
                    throw ValidationException::withMessages(['resolution' => 'The damaged quantity is no longer in transit.']);
                }
                $destination = $this->lockedLevel($transfer, $item, false);
                $this->moveDamaged($destination, $quantity, $transfer, $item, $user);
                $this->moveBatchToDamaged($transfer, $item, $quantity);
                $item->update(['in_transit_quantity' => (float) $item->in_transit_quantity - $quantity]);
            } elseif (! in_array($resolution, ['confirm_loss', 'manager_adjustment'], true)) {
                throw ValidationException::withMessages(['resolution' => 'Choose a valid discrepancy resolution.']);
            }

            $discrepancy->update(['status' => 'resolved', 'resolution' => $resolution, 'notes' => $notes ?: $discrepancy->notes, 'resolved_by' => $user->id, 'resolved_at' => now()]);
            $this->refreshStatus($transfer, $user);
            $this->audit('inventory.transfer.discrepancy_resolved', $transfer, $user, 'Stock transfer discrepancy resolved.', ['discrepancy_id' => $discrepancy->id, 'resolution' => $resolution]);

            return $discrepancy->refresh();
        });
    }

    private function locked(int $id, User $user): StockTransfer
    {
        return StockTransfer::query()->where('company_id', $user->company_id)->with($this->relations())->lockForUpdate()->findOrFail($id);
    }

    private function authorizePair(StockTransfer $transfer, User $user): void
    {
        $this->locations->authorize($user, $transfer->source_warehouse_id);
        $this->locations->authorize($user, $transfer->destination_warehouse_id);
    }

    private function authorizeEither(StockTransfer $transfer, User $user): void
    {
        if (! $this->locations->query($user, false)->whereIn('id', [$transfer->source_warehouse_id, $transfer->destination_warehouse_id])->exists()) {
            throw ValidationException::withMessages(['transfer' => 'That transfer is not available to your stock locations.']);
        }
    }

    private function lockedLevel(StockTransfer $transfer, StockTransferItem $item, bool $source): StockLevel
    {
        $warehouseId = $source ? $transfer->source_warehouse_id : $transfer->destination_warehouse_id;
        $locationId = $source ? $item->source_stock_location_id : $item->destination_stock_location_id;
        $branchId = $source ? $transfer->source_branch_id : $transfer->destination_branch_id;
        $level = StockLevel::query()->firstOrCreate([
            'company_id' => $transfer->company_id,
            'warehouse_id' => $warehouseId,
            'stock_location_id' => $locationId,
            'product_id' => $item->product_id,
        ], [
            'branch_id' => $branchId,
            'quantity_on_hand' => 0,
            'quantity_available' => 0,
            'quantity_reserved' => 0,
            'quantity_damaged' => 0,
        ]);

        return StockLevel::query()->lockForUpdate()->findOrFail($level->id);
    }

    private function moveAvailable(StockLevel $level, float $change, string $type, string $direction, string $fromState, string $toState, StockTransfer $transfer, StockTransferItem $item, User $user): void
    {
        $before = (float) $level->quantity_on_hand;
        $after = $before + $change;
        $available = (float) $level->quantity_available + $change;
        $level->update(['branch_id' => $level->branch_id ?: ($level->warehouse?->branch_id), 'quantity_on_hand' => $after, 'quantity_available' => $available, 'last_stock_movement_at' => now()]);
        StockMovement::create([
            'company_id' => $transfer->company_id,
            'branch_id' => $level->branch_id,
            'warehouse_id' => $level->warehouse_id,
            'stock_location_id' => $level->stock_location_id,
            'product_id' => $item->product_id,
            'inventory_batch_id' => $item->inventory_batch_id,
            'movement_type' => $type,
            'direction' => $direction,
            'from_stock_state' => $fromState,
            'to_stock_state' => $toState,
            'quantity' => abs($change),
            'quantity_before' => $before,
            'quantity_after' => $after,
            'unit_cost' => $item->product->cost_price,
            'reference_type' => StockTransfer::class,
            'reference_id' => $transfer->id,
            'reason' => 'Stock transfer '.$transfer->transfer_number,
            'created_by' => $user->id,
            'occurred_at' => now(),
        ]);
    }

    private function moveDamaged(StockLevel $level, float $quantity, StockTransfer $transfer, StockTransferItem $item, User $user): void
    {
        $before = (float) $level->quantity_on_hand;
        $after = $before + $quantity;
        $level->update(['quantity_on_hand' => $after, 'quantity_damaged' => (float) $level->quantity_damaged + $quantity, 'last_stock_movement_at' => now()]);
        StockMovement::create([
            'company_id' => $transfer->company_id,
            'branch_id' => $level->branch_id,
            'warehouse_id' => $level->warehouse_id,
            'stock_location_id' => $level->stock_location_id,
            'product_id' => $item->product_id,
            'inventory_batch_id' => $item->inventory_batch_id,
            'movement_type' => 'transfer_damage',
            'direction' => 'in',
            'from_stock_state' => 'in_transit',
            'to_stock_state' => 'damaged',
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'unit_cost' => $item->product->cost_price,
            'reference_type' => StockTransfer::class,
            'reference_id' => $transfer->id,
            'reason' => 'Damaged during transfer '.$transfer->transfer_number,
            'created_by' => $user->id,
            'occurred_at' => now(),
        ]);
    }

    private function discrepancy(StockTransfer $transfer, StockTransferItem $item, InventoryTransferReceipt $receipt, string $type, float $expected, float $actual, float $quantity, User $user, ?string $notes): void
    {
        InventoryTransferDiscrepancy::create([
            'company_id' => $transfer->company_id,
            'stock_transfer_id' => $transfer->id,
            'stock_transfer_item_id' => $item->id,
            'inventory_transfer_receipt_id' => $receipt->id,
            'type' => $type,
            'reason' => $notes,
            'expected_quantity' => $expected,
            'actual_quantity' => $actual,
            'discrepancy_quantity' => $quantity,
            'notes' => $notes,
            'reported_by' => $user->id,
        ]);
        $this->audit('inventory.transfer.discrepancy_recorded', $transfer, $user, 'Stock transfer discrepancy recorded.', ['type' => $type, 'quantity' => $quantity, 'product_id' => $item->product_id]);
    }

    private function moveBatch(StockTransfer $transfer, StockTransferItem $item, float $change, bool $source): void
    {
        if (! $item->inventory_batch_id) {
            return;
        }
        $batch = InventoryBatch::query()->where('company_id', $transfer->company_id)->lockForUpdate()->findOrFail($item->inventory_batch_id);
        if ($source) {
            $onHand = (float) $batch->quantity_on_hand + $change;
            $available = (float) $batch->quantity_available + $change;
            if ($onHand < -0.0005 || $available < -0.0005) {
                throw ValidationException::withMessages(['items' => "Insufficient batch stock for {$item->product->name}."]);
            }
            $batch->update(['quantity_on_hand' => $onHand, 'quantity_available' => $available]);

            return;
        }
        $destination = $this->destinationBatch($transfer, $item, $batch);
        $destination->update(['quantity_on_hand' => (float) $destination->quantity_on_hand + $change, 'quantity_available' => (float) $destination->quantity_available + $change]);
    }

    private function moveBatchToDamaged(StockTransfer $transfer, StockTransferItem $item, float $quantity): void
    {
        if (! $item->inventory_batch_id) {
            return;
        }
        $source = InventoryBatch::query()->where('company_id', $transfer->company_id)->findOrFail($item->inventory_batch_id);
        $destination = $this->destinationBatch($transfer, $item, $source);
        $destination->update(['quantity_on_hand' => (float) $destination->quantity_on_hand + $quantity]);
    }

    private function destinationBatch(StockTransfer $transfer, StockTransferItem $item, InventoryBatch $source): InventoryBatch
    {
        $destination = InventoryBatch::query()->firstOrCreate([
            'company_id' => $transfer->company_id,
            'product_id' => $item->product_id,
            'warehouse_id' => $transfer->destination_warehouse_id,
            'stock_location_id' => $item->destination_stock_location_id,
            'batch_number' => $source->batch_number,
        ], [
            'manufactured_at' => $source->manufactured_at,
            'expires_at' => $source->expires_at,
            'unit_cost' => $source->unit_cost,
            'supplier_reference' => $source->supplier_reference,
            'receipt_reference' => $transfer->transfer_number,
            'quantity_on_hand' => 0,
            'quantity_available' => 0,
            'status' => $source->status,
        ]);

        return InventoryBatch::query()->lockForUpdate()->findOrFail($destination->id);
    }

    private function refreshStatus(StockTransfer $transfer, User $user): void
    {
        $transfer->refresh();
        $openDiscrepancy = $transfer->discrepancies()->where('status', 'open')->exists();
        $inTransit = (float) $transfer->items()->sum('in_transit_quantity');
        $status = $openDiscrepancy ? StockTransfer::DISCREPANCY : ($inTransit > 0 ? StockTransfer::PARTIALLY_RECEIVED : StockTransfer::RECEIVED);
        $transfer->update(['status' => $status, 'received_by' => $status === StockTransfer::RECEIVED ? $user->id : null, 'received_at' => $status === StockTransfer::RECEIVED ? now() : null]);
    }

    private function releaseReservedSerials(StockTransfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            foreach ($item->serialNumbers->where('status', 'reserved') as $serial) {
                $serial->update(['status' => 'available', 'reference_type' => null, 'reference_id' => null]);
                $item->serialNumbers()->updateExistingPivot($serial->id, ['status' => 'released']);
            }
        }
    }

    private function locationId(User $user, Warehouse $warehouse, mixed $locationId, string $key): ?int
    {
        if (! $locationId) {
            return null;
        }
        $exists = StockLocation::query()->where('company_id', $user->company_id)->where('warehouse_id', $warehouse->id)->where('is_active', true)->whereKey((int) $locationId)->exists();
        if (! $exists) {
            throw ValidationException::withMessages([$key => 'Choose an active bin in the selected location.']);
        }

        return (int) $locationId;
    }

    private function setting(int $companyId, string $key, bool $default): bool
    {
        $value = Setting::query()->where('company_id', $companyId)->where('group', 'inventory')->where('key', $key)->value('value');

        return (bool) data_get($value, 'value', $default);
    }

    private function warehouseForOutlet(User $user, int $branchId): Warehouse
    {
        return $this->locations->query($user)
            ->where('branch_id', $branchId)
            ->orderByDesc('is_primary')
            ->firstOrFail();
    }

    private function nextNumber(): string
    {
        return 'TRF-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    private function positive(mixed $value, string $message): float
    {
        $quantity = (float) $value;
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['items' => $message]);
        }

        return $quantity;
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return ['sourceOutlet', 'destinationOutlet', 'sourceWarehouse', 'destinationWarehouse', 'sourceLocation', 'destinationLocation', 'requester', 'approver', 'packer', 'items.product.unit', 'items.sourceLocation', 'items.destinationLocation', 'items.serialNumbers', 'receipts.items', 'discrepancies.transferItem.product'];
    }

    /** @param array<string, mixed> $context */
    private function audit(string $event, StockTransfer $transfer, User $user, string $description, array $context = []): void
    {
        $this->audit->record($event, $transfer, $description, $context + [
            'company_id' => $transfer->company_id,
            'transfer_id' => $transfer->id,
            'source_warehouse_id' => $transfer->source_warehouse_id,
            'destination_warehouse_id' => $transfer->destination_warehouse_id,
            'actor_id' => $user->id,
        ]);
    }
}
