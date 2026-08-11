<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventorySerialNumber;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryTraceabilityService
{
    public function __construct(
        private readonly InventoryLocationAccessService $locations,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function saveBatch(User $user, array $data, ?InventoryBatch $batch = null): InventoryBatch
    {
        return DB::transaction(function () use ($user, $data, $batch): InventoryBatch {
            $warehouse = $this->locations->authorize($user, (int) $data['warehouse_id']);
            $product = Product::query()->where('company_id', $user->company_id)->findOrFail((int) $data['product_id']);
            if (! $product->track_batches) {
                throw ValidationException::withMessages(['product_id' => 'Enable batch tracking on this product before adding batches.']);
            }
            $locationId = $this->location($user, $warehouse->id, $data['stock_location_id'] ?? null);
            if ($batch && ($batch->company_id !== $user->company_id || $batch->product_id !== $product->id)) {
                abort(404);
            }
            $quantity = (float) $data['quantity_on_hand'];
            $quantityAvailable = (float) ($data['quantity_available'] ?? $quantity);
            if ($quantityAvailable > $quantity + 0.0005) {
                throw ValidationException::withMessages(['quantity_available' => 'Available batch quantity cannot exceed its on-hand quantity.']);
            }
            $available = (float) StockLevel::query()->where('company_id', $user->company_id)->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->when($locationId, fn ($query) => $query->where('stock_location_id', $locationId), fn ($query) => $query->whereNull('stock_location_id'))->sum('quantity_on_hand');
            $allocated = (float) InventoryBatch::query()->where('company_id', $user->company_id)->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->when($locationId, fn ($query) => $query->where('stock_location_id', $locationId), fn ($query) => $query->whereNull('stock_location_id'))->when($batch, fn ($query) => $query->whereKeyNot($batch->id))->sum('quantity_on_hand');
            if ($allocated + $quantity > $available + 0.0005) {
                throw ValidationException::withMessages(['quantity_on_hand' => 'Batch quantities cannot exceed the physical stock at this location.']);
            }
            $payload = [
                'company_id' => $user->company_id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'stock_location_id' => $locationId,
                'batch_number' => trim((string) $data['batch_number']),
                'manufactured_at' => $data['manufactured_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'quantity_on_hand' => $quantity,
                'quantity_available' => $quantityAvailable,
                'unit_cost' => $data['unit_cost'] ?? $product->cost_price,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'receipt_reference' => $data['receipt_reference'] ?? null,
                'status' => $data['status'] ?? 'active',
            ];
            $model = $batch ? tap($batch)->update($payload) : InventoryBatch::create($payload);
            $this->audit->record($batch ? 'inventory.batch.updated' : 'inventory.batch.created', $model, $batch ? 'Inventory batch updated.' : 'Inventory batch created.', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id]);

            return $model->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function createSerials(User $user, array $data): int
    {
        return DB::transaction(function () use ($user, $data): int {
            $warehouse = $this->locations->authorize($user, (int) $data['warehouse_id']);
            $product = Product::query()->where('company_id', $user->company_id)->findOrFail((int) $data['product_id']);
            if (! $product->track_serials) {
                throw ValidationException::withMessages(['product_id' => 'Enable serial tracking on this product before adding serial numbers.']);
            }
            $locationId = $this->location($user, $warehouse->id, $data['stock_location_id'] ?? null);
            $batchId = $data['inventory_batch_id'] ?? null;
            if ($batchId && ! InventoryBatch::query()
                ->where('company_id', $user->company_id)
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->when($locationId, fn ($query) => $query->where('stock_location_id', $locationId), fn ($query) => $query->whereNull('stock_location_id'))
                ->whereKey((int) $batchId)
                ->exists()) {
                throw ValidationException::withMessages(['inventory_batch_id' => 'Choose a batch for this product and stock location.']);
            }
            $serials = collect(preg_split('/[\r\n,]+/', (string) $data['serial_numbers']))->map(fn ($value) => trim((string) $value))->filter()->unique()->values();
            if ($serials->isEmpty() || $serials->count() > 500) {
                throw ValidationException::withMessages(['serial_numbers' => 'Enter between 1 and 500 unique serial numbers.']);
            }
            $duplicates = InventorySerialNumber::query()->where('company_id', $user->company_id)->where('product_id', $product->id)->whereIn('serial_number', $serials)->pluck('serial_number');
            if ($duplicates->isNotEmpty()) {
                throw ValidationException::withMessages(['serial_numbers' => 'Some serial numbers already exist for this product.']);
            }
            $stock = (float) StockLevel::query()->where('company_id', $user->company_id)->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->when($locationId, fn ($query) => $query->where('stock_location_id', $locationId), fn ($query) => $query->whereNull('stock_location_id'))->sum('quantity_on_hand');
            $existing = InventorySerialNumber::query()->where('company_id', $user->company_id)->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->when($locationId, fn ($query) => $query->where('stock_location_id', $locationId), fn ($query) => $query->whereNull('stock_location_id'))->whereIn('status', ['available', 'reserved'])->count();
            if ($existing + $serials->count() > floor($stock + 0.0005)) {
                throw ValidationException::withMessages(['serial_numbers' => 'Serial numbers cannot exceed physical stock at this location.']);
            }
            foreach ($serials as $serial) {
                InventorySerialNumber::create(['company_id' => $user->company_id, 'product_id' => $product->id, 'inventory_batch_id' => $batchId, 'warehouse_id' => $warehouse->id, 'stock_location_id' => $locationId, 'serial_number' => $serial, 'status' => 'available']);
            }
            $this->audit->record('inventory.serial.created', $product, 'Inventory serial numbers added.', ['warehouse_id' => $warehouse->id, 'count' => $serials->count()]);

            return $serials->count();
        });
    }

    /** @param array<string, mixed> $data */
    public function updateSerial(User $user, InventorySerialNumber $serial, array $data): InventorySerialNumber
    {
        return DB::transaction(function () use ($user, $serial, $data): InventorySerialNumber {
            $serial = InventorySerialNumber::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($serial->id);
            $this->locations->authorizeSerial($user, $serial);
            $status = (string) $data['status'];
            $warehouseId = $data['warehouse_id'] ?? $serial->warehouse_id;
            $locationId = $data['stock_location_id'] ?? $serial->stock_location_id;
            if (in_array($status, ['in_transit', 'sold'], true)) {
                $warehouseId = null;
                $locationId = null;
            } elseif (! $warehouseId) {
                throw ValidationException::withMessages(['warehouse_id' => 'Choose a location for this serial status.']);
            } else {
                $warehouse = $this->locations->authorize($user, (int) $warehouseId);
                $locationId = $this->location($user, $warehouse->id, $locationId);
            }
            $before = ['status' => $serial->status, 'warehouse_id' => $serial->warehouse_id, 'stock_location_id' => $serial->stock_location_id];
            $serial->update(['status' => $status, 'warehouse_id' => $warehouseId, 'stock_location_id' => $locationId]);
            $this->audit->record('inventory.serial.status_changed', $serial, 'Inventory serial status changed.', ['before' => $before, 'after' => ['status' => $status, 'warehouse_id' => $warehouseId, 'stock_location_id' => $locationId]]);

            return $serial->refresh();
        });
    }

    private function location(User $user, int $warehouseId, mixed $locationId): ?int
    {
        if (! $locationId) {
            return null;
        }
        if (! StockLocation::query()->where('company_id', $user->company_id)->where('warehouse_id', $warehouseId)->where('is_active', true)->whereKey((int) $locationId)->exists()) {
            throw ValidationException::withMessages(['stock_location_id' => 'Choose an active bin in the selected location.']);
        }

        return (int) $locationId;
    }
}
