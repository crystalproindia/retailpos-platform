<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\StockCountStatus;
use App\Models\Inventory\InventoryStockCount;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockCountService
{
    public function __construct(
        private readonly InventoryLocationAccessService $locations,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): InventoryStockCount
    {
        return DB::transaction(function () use ($user, $data): InventoryStockCount {
            $warehouse = $this->locations->authorize($user, (int) $data['warehouse_id']);
            $locationId = $this->location($user, $warehouse->id, $data['stock_location_id'] ?? null);
            $productIds = collect($data['product_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
            $products = Product::query()
                ->where('company_id', $user->company_id)
                ->where('track_inventory', true)
                ->where('is_active', true)
                ->when(($data['type'] ?? 'full') === 'category', fn ($query) => $query->where('category_id', $data['category_id']))
                ->when($productIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $productIds))
                ->when(($data['type'] ?? 'full') === 'full' && $productIds->isEmpty(), fn ($query) => $query->whereHas('stockLevels', fn ($stock) => $stock->where('warehouse_id', $warehouse->id)->when($locationId, fn ($row) => $row->where('stock_location_id', $locationId))))
                ->orderBy('name')
                ->get();
            if ($products->isEmpty()) {
                throw ValidationException::withMessages(['product_ids' => 'Choose at least one stocked product for this count.']);
            }

            if (! empty($data['assigned_to'])) {
                User::query()->where('company_id', $user->company_id)->where('is_active', true)->findOrFail((int) $data['assigned_to']);
            }
            $count = InventoryStockCount::create([
                'company_id' => $user->company_id,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'stock_location_id' => $locationId,
                'count_number' => 'CNT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'type' => $data['type'] ?? 'full',
                'status' => StockCountStatus::Draft,
                'assigned_to' => $data['assigned_to'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);
            $levels = StockLevel::query()
                ->where('company_id', $user->company_id)
                ->where('warehouse_id', $warehouse->id)
                ->when($locationId, fn ($query) => $query->where('stock_location_id', $locationId))
                ->whereIn('product_id', $products->pluck('id'))
                ->orderByRaw('stock_location_id IS NULL DESC')
                ->orderBy('stock_location_id')
                ->get()
                ->groupBy('product_id');
            foreach ($products as $product) {
                $productLevels = $levels->get($product->id, collect());
                if ($productLevels->isEmpty()) {
                    $productLevels = collect([null]);
                }
                foreach ($productLevels as $level) {
                    $count->items()->create([
                        'product_id' => $product->id,
                        'stock_location_id' => $level?->stock_location_id ?? $locationId,
                        'system_quantity' => (float) ($level?->quantity_on_hand ?? 0),
                        'unit_cost' => $product->cost_price ?? $product->purchase_price ?? 0,
                    ]);
                }
            }
            $this->audit->record('inventory.count.created', $count, 'Physical stock count created.', ['warehouse_id' => $warehouse->id, 'items_count' => $products->count()]);

            return $count->load('items.product');
        });
    }

    /** @param array<int, array<string, mixed>> $items */
    public function record(InventoryStockCount $count, User $user, array $items): InventoryStockCount
    {
        return DB::transaction(function () use ($count, $user, $items): InventoryStockCount {
            $count = $this->locked($count, $user);
            if (! in_array($count->status, [StockCountStatus::Draft, StockCountStatus::Counting], true)) {
                throw ValidationException::withMessages(['count' => 'Only an active count can be updated.']);
            }
            $input = collect($items)->keyBy(fn (array $item) => (int) $item['id']);
            foreach ($count->items as $item) {
                if (! $input->has($item->id) || $input[$item->id]['counted_quantity'] === null || $input[$item->id]['counted_quantity'] === '') {
                    continue;
                }
                $quantity = (float) $input[$item->id]['counted_quantity'];
                if ($quantity < 0 && ! $item->product->allow_negative_stock) {
                    throw ValidationException::withMessages(['items' => "{$item->product->name} cannot have a negative physical count."]);
                }
                $item->update(['counted_quantity' => $quantity, 'variance_quantity' => $quantity - (float) $item->system_quantity, 'notes' => $input[$item->id]['notes'] ?? null, 'counted_by' => $user->id, 'counted_at' => now()]);
            }
            $count->update(['status' => StockCountStatus::Counting, 'started_at' => $count->started_at ?? now()]);
            $this->audit->record('inventory.count.updated', $count, 'Physical stock quantities saved.');

            return $count->refresh()->load('items.product');
        });
    }

    public function submit(InventoryStockCount $count, User $user): InventoryStockCount
    {
        return DB::transaction(function () use ($count, $user): InventoryStockCount {
            $count = $this->locked($count, $user);
            if (! in_array($count->status, [StockCountStatus::Draft, StockCountStatus::Counting], true)) {
                return $count;
            }
            if ($count->items->contains(fn ($item) => $item->counted_quantity === null)) {
                throw ValidationException::withMessages(['items' => 'Count every listed product before submitting.']);
            }
            $count->update(['status' => StockCountStatus::Submitted, 'submitted_by' => $user->id, 'submitted_at' => now()]);
            $this->audit->record('inventory.count.submitted', $count, 'Physical stock count submitted for review.');

            return $count->refresh();
        });
    }

    public function approve(InventoryStockCount $count, User $user): InventoryStockCount
    {
        return DB::transaction(function () use ($count, $user): InventoryStockCount {
            $count = $this->locked($count, $user);
            if ($count->status === StockCountStatus::Approved) {
                return $count;
            }
            if (! in_array($count->status, [StockCountStatus::Submitted, StockCountStatus::Review], true)) {
                throw ValidationException::withMessages(['count' => 'Only a submitted count can be approved.']);
            }
            $count->update(['status' => StockCountStatus::Approved, 'approved_by' => $user->id, 'approved_at' => now()]);
            $this->audit->record('inventory.count.approved', $count, 'Physical stock count approved.');

            return $count->refresh();
        });
    }

    public function post(InventoryStockCount $count, User $user): InventoryStockCount
    {
        return DB::transaction(function () use ($count, $user): InventoryStockCount {
            $count = $this->locked($count, $user);
            if ($count->status === StockCountStatus::Posted) {
                return $count;
            }
            if ($count->status !== StockCountStatus::Approved) {
                throw ValidationException::withMessages(['count' => 'Approve the count before posting stock changes.']);
            }
            foreach ($count->items as $item) {
                $level = StockLevel::query()->firstOrCreate([
                    'company_id' => $count->company_id,
                    'warehouse_id' => $count->warehouse_id,
                    'stock_location_id' => $item->stock_location_id,
                    'product_id' => $item->product_id,
                ], ['branch_id' => $count->branch_id, 'quantity_on_hand' => 0, 'quantity_reserved' => 0, 'quantity_damaged' => 0, 'quantity_available' => 0]);
                $level = StockLevel::query()->lockForUpdate()->findOrFail($level->id);
                $before = (float) $level->quantity_on_hand;
                if (abs($before - (float) $item->system_quantity) > 0.0005) {
                    throw ValidationException::withMessages(['count' => "Stock changed for {$item->product->name} after this count started. Create a fresh count before posting."]);
                }
                $after = (float) $item->counted_quantity;
                $difference = $after - $before;
                if (abs($difference) < 0.0005) {
                    continue;
                }
                $level->update(['quantity_on_hand' => $after, 'quantity_available' => $after - (float) $level->quantity_reserved - (float) $level->quantity_damaged, 'last_stock_movement_at' => now()]);
                StockMovement::create([
                    'company_id' => $count->company_id,
                    'branch_id' => $count->branch_id,
                    'warehouse_id' => $count->warehouse_id,
                    'stock_location_id' => $item->stock_location_id,
                    'product_id' => $item->product_id,
                    'movement_type' => 'physical_count',
                    'direction' => $difference > 0 ? 'in' : 'out',
                    'from_stock_state' => 'count_snapshot',
                    'to_stock_state' => 'available',
                    'quantity' => abs($difference),
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'unit_cost' => $item->unit_cost,
                    'reference_type' => InventoryStockCount::class,
                    'reference_id' => $count->id,
                    'reason' => 'Physical count '.$count->count_number,
                    'notes' => $item->notes,
                    'created_by' => $user->id,
                    'occurred_at' => now(),
                ]);
            }
            $count->update(['status' => StockCountStatus::Posted, 'posted_by' => $user->id, 'posted_at' => now()]);
            $this->audit->record('inventory.count.posted', $count, 'Physical stock count posted to the stock ledger.');

            return $count->refresh();
        });
    }

    private function locked(InventoryStockCount $count, User $user): InventoryStockCount
    {
        $this->locations->authorize($user, $count->warehouse_id);

        return InventoryStockCount::query()->where('company_id', $user->company_id)->with('items.product')->lockForUpdate()->findOrFail($count->id);
    }

    private function location(User $user, int $warehouseId, mixed $locationId): ?int
    {
        if (! $locationId) {
            return null;
        }
        $exists = StockLocation::query()->where('company_id', $user->company_id)->where('warehouse_id', $warehouseId)->where('is_active', true)->whereKey((int) $locationId)->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['stock_location_id' => 'Choose an active bin in the selected location.']);
        }

        return (int) $locationId;
    }
}
