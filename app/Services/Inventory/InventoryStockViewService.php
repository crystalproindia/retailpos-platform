<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventorySerialNumber;
use App\Models\Inventory\InventoryStockCountItem;
use App\Models\Inventory\InventoryTransferDiscrepancy;
use App\Models\Inventory\Product;
use App\Models\Inventory\ReorderRule;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InventoryStockViewService
{
    private const ROW_LIMIT = 500;

    public function __construct(private readonly InventoryLocationAccessService $locations) {}

    /** @param array<string, mixed> $filters @return LengthAwarePaginator<int, Product> */
    public function availability(User $user, array $filters): LengthAwarePaginator
    {
        $warehouseIds = $this->warehouseIds($user, $filters);
        $term = trim((string) ($filters['search'] ?? ''));

        return Product::query()
            ->with(['unit', 'stockLevels' => fn ($query) => $query->with(['warehouse.branch', 'location'])->whereIn('warehouse_id', $warehouseIds)])
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $search) => $search
                ->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();
    }

    /** @return array<string, mixed> */
    public function product(User $user, Product $product): array
    {
        abort_unless($product->company_id === $user->company_id, 404);
        $warehouseIds = $this->locations->accessibleWarehouses($user, false)->pluck('id');
        $levels = StockLevel::query()
            ->with(['warehouse.branch', 'location'])
            ->where('company_id', $user->company_id)
            ->where('product_id', $product->id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->orderBy('warehouse_id')
            ->get();
        $transit = StockTransferItem::query()
            ->where('product_id', $product->id)
            ->where('in_transit_quantity', '>', 0)
            ->whereHas('transfer', fn (Builder $query) => $query
                ->where('company_id', $user->company_id)
                ->where(fn (Builder $scope) => $scope->whereIn('source_warehouse_id', $warehouseIds)->orWhereIn('destination_warehouse_id', $warehouseIds)))
            ->sum('in_transit_quantity');

        return [
            'levels' => $levels,
            'total_on_hand' => (float) $levels->sum('quantity_on_hand'),
            'total_available' => (float) $levels->sum('quantity_available'),
            'total_reserved' => (float) $levels->sum('quantity_reserved'),
            'total_damaged' => (float) $levels->sum('quantity_damaged'),
            'total_in_transit' => (float) $transit,
            'batches' => InventoryBatch::query()->with(['warehouse', 'location'])->where('company_id', $user->company_id)->where('product_id', $product->id)->whereIn('warehouse_id', $warehouseIds)->orderBy('expires_at')->limit(50)->get(),
            'serials' => $this->locations->scopeSerials(InventorySerialNumber::query()->with(['warehouse', 'location'])->where('product_id', $product->id), $user)->latest()->limit(50)->get(),
            'movements' => StockMovement::query()->with(['warehouse', 'location', 'creator'])->where('company_id', $user->company_id)->where('product_id', $product->id)->whereIn('warehouse_id', $warehouseIds)->latest('occurred_at')->limit(50)->get(),
            'transfers' => StockTransfer::query()->with(['sourceWarehouse', 'destinationWarehouse', 'items' => fn ($query) => $query->where('product_id', $product->id)])->where('company_id', $user->company_id)->whereHas('items', fn (Builder $query) => $query->where('product_id', $product->id))->where(fn (Builder $scope) => $scope->whereIn('source_warehouse_id', $warehouseIds)->orWhereIn('destination_warehouse_id', $warehouseIds))->latest()->limit(10)->get(),
            'adjustments' => StockAdjustment::query()->with('warehouse')->where('company_id', $user->company_id)->whereIn('warehouse_id', $warehouseIds)->whereHas('items', fn (Builder $query) => $query->where('product_id', $product->id))->latest()->limit(10)->get(),
            'reorder_rules' => ReorderRule::query()->with(['warehouse', 'location'])->where('company_id', $user->company_id)->where('product_id', $product->id)->whereIn('warehouse_id', $warehouseIds)->get(),
            'last_sale' => StockMovement::query()->where('company_id', $user->company_id)->where('product_id', $product->id)->whereIn('warehouse_id', $warehouseIds)->where('movement_type', 'sale')->max('occurred_at'),
            'last_purchase' => StockMovement::query()->where('company_id', $user->company_id)->where('product_id', $product->id)->whereIn('warehouse_id', $warehouseIds)->where('movement_type', 'purchase')->max('occurred_at'),
        ];
    }

    /** @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    public function reportRows(User $user, string $report, array $filters): Collection
    {
        $warehouseIds = $this->warehouseIds($user, $filters);
        $productId = filled($filters['product_id'] ?? null) ? (int) $filters['product_id'] : null;

        return match ($report) {
            'stock-by-location', 'stock-valuation', 'low-stock', 'ageing', 'slow-dead' => $this->stockRows($user, $warehouseIds, $productId, $report),
            'stock-movement' => $this->movementRows($user, $warehouseIds, $productId, $filters),
            'transfers' => $this->transferRows($user, $warehouseIds, $filters),
            'in-transit' => $this->inTransitRows($user, $warehouseIds, $productId, $filters),
            'discrepancies' => $this->discrepancyRows($user, $warehouseIds, $filters),
            'adjustments' => $this->adjustmentRows($user, $warehouseIds, $filters),
            'count-variance' => $this->countRows($user, $warehouseIds, $productId, $filters),
            'batches', 'expiry' => $this->batchRows($user, $warehouseIds, $productId, $filters, $report === 'expiry'),
            'serials' => $this->serialRows($user, $warehouseIds, $productId, $filters),
            'reorder' => $this->reorderRows($user, $warehouseIds, $productId),
            default => collect(),
        };
    }

    /** @param Collection<int, int> $warehouseIds @return Collection<int, array<string, mixed>> */
    private function stockRows(User $user, Collection $warehouseIds, ?int $productId, string $report): Collection
    {
        return StockLevel::query()
            ->with(['product', 'warehouse', 'location'])
            ->where('company_id', $user->company_id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->when($productId, fn ($query) => $query->where('product_id', $productId))
            ->when($report === 'low-stock', fn ($query) => $query->whereColumn('quantity_available', '<=', 'reorder_point'))
            ->when($report === 'slow-dead', fn ($query) => $query->where('quantity_on_hand', '>', 0)->where(fn ($scope) => $scope->whereNull('last_stock_movement_at')->orWhere('last_stock_movement_at', '<=', now()->subDays(91))))
            ->orderBy('warehouse_id')
            ->limit(self::ROW_LIMIT)
            ->get()
            ->map(function (StockLevel $level): array {
                $days = $level->last_stock_movement_at?->diffInDays(now());
                $unitCostMinor = (int) round((float) ($level->product?->cost_price ?? $level->product?->purchase_price ?? 0) * 100);
                $valueMinor = (int) round((float) $level->quantity_on_hand * $unitCostMinor);

                return [
                    'product' => $level->product?->name,
                    'sku' => $level->product?->sku,
                    'location' => $level->warehouse?->name,
                    'bin' => $level->location?->code,
                    'on_hand' => (string) $level->quantity_on_hand,
                    'available' => (string) $level->quantity_available,
                    'reserved' => (string) $level->quantity_reserved,
                    'damaged' => (string) $level->quantity_damaged,
                    'stock_value_minor' => $valueMinor,
                    'stock_value' => number_format($valueMinor / 100, 2, '.', ''),
                    'age_days' => $days,
                    'classification' => match (true) {
                        $days === null => 'No movement',
                        $days <= 30 => 'Fast moving',
                        $days <= 90 => 'Normal',
                        $days <= 180 => 'Slow moving',
                        default => 'Dead stock candidate',
                    },
                ];
            });
    }

    /** @param Collection<int, int> $warehouseIds @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    private function movementRows(User $user, Collection $warehouseIds, ?int $productId, array $filters): Collection
    {
        $query = StockMovement::query()->with(['product', 'warehouse', 'location', 'creator'])->where('company_id', $user->company_id)->whereIn('warehouse_id', $warehouseIds)->when($productId, fn ($builder) => $builder->where('product_id', $productId));
        $this->dateRange($query, $filters, 'occurred_at');

        return $query->latest('occurred_at')->limit(self::ROW_LIMIT)->get()->map(fn (StockMovement $row) => [
            'date' => $row->occurred_at?->toDateTimeString(),
            'product' => $row->product?->name,
            'sku' => $row->product?->sku,
            'location' => $row->warehouse?->name,
            'bin' => $row->location?->code,
            'movement' => $row->movement_type,
            'quantity_in' => $row->direction === 'in' ? (string) $row->quantity : '0.000',
            'quantity_out' => $row->direction === 'out' ? (string) $row->quantity : '0.000',
            'balance' => (string) $row->quantity_after,
            'user' => $row->creator?->name,
        ]);
    }

    /** @param Collection<int, int> $warehouseIds @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    private function transferRows(User $user, Collection $warehouseIds, array $filters): Collection
    {
        $query = StockTransfer::query()->with(['sourceWarehouse', 'destinationWarehouse', 'requester'])->where('company_id', $user->company_id)->where(fn (Builder $scope) => $scope->whereIn('source_warehouse_id', $warehouseIds)->orWhereIn('destination_warehouse_id', $warehouseIds));
        $this->dateRange($query, $filters, 'created_at');

        return $query->latest()->limit(self::ROW_LIMIT)->get()->map(fn (StockTransfer $row) => [
            'transfer' => $row->transfer_number,
            'from' => $row->sourceWarehouse?->name,
            'to' => $row->destinationWarehouse?->name,
            'status' => $row->status,
            'requested_by' => $row->requester?->name,
            'requested_at' => $row->requested_at?->toDateTimeString(),
            'dispatched_at' => $row->dispatched_at?->toDateTimeString(),
            'received_at' => $row->received_at?->toDateTimeString(),
        ]);
    }

    /** @param Collection<int, int> $warehouseIds @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    private function inTransitRows(User $user, Collection $warehouseIds, ?int $productId, array $filters): Collection
    {
        $query = StockTransferItem::query()->with(['product', 'transfer.sourceWarehouse', 'transfer.destinationWarehouse'])->where('in_transit_quantity', '>', 0)->when($productId, fn ($builder) => $builder->where('product_id', $productId))->whereHas('transfer', function (Builder $transfer) use ($user, $warehouseIds, $filters): void {
            $transfer->where('company_id', $user->company_id)->where(fn (Builder $scope) => $scope->whereIn('source_warehouse_id', $warehouseIds)->orWhereIn('destination_warehouse_id', $warehouseIds));
            $this->dateRange($transfer, $filters, 'created_at');
        });

        return $query->limit(self::ROW_LIMIT)->get()->map(fn (StockTransferItem $row) => [
            'transfer' => $row->transfer?->transfer_number,
            'product' => $row->product?->name,
            'sku' => $row->product?->sku,
            'from' => $row->transfer?->sourceWarehouse?->name,
            'to' => $row->transfer?->destinationWarehouse?->name,
            'dispatched' => (string) $row->dispatched_quantity,
            'received' => (string) $row->received_quantity,
            'in_transit' => (string) $row->in_transit_quantity,
        ]);
    }

    /** @param Collection<int, int> $warehouseIds @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    private function discrepancyRows(User $user, Collection $warehouseIds, array $filters): Collection
    {
        $query = InventoryTransferDiscrepancy::query()->with(['transferItem.product', 'transfer.sourceWarehouse', 'transfer.destinationWarehouse', 'reporter', 'resolver'])->where('company_id', $user->company_id)->whereHas('transfer', fn (Builder $transfer) => $transfer->where(fn (Builder $scope) => $scope->whereIn('source_warehouse_id', $warehouseIds)->orWhereIn('destination_warehouse_id', $warehouseIds)));
        $this->dateRange($query, $filters, 'created_at');

        return $query->latest()->limit(self::ROW_LIMIT)->get()->map(fn (InventoryTransferDiscrepancy $row) => [
            'transfer' => $row->transfer?->transfer_number,
            'product' => $row->transferItem?->product?->name,
            'type' => $row->type,
            'reason' => $row->reason,
            'quantity' => (string) $row->discrepancy_quantity,
            'status' => $row->status,
            'resolution' => $row->resolution,
            'reported_by' => $row->reporter?->name,
            'resolved_by' => $row->resolver?->name,
        ]);
    }

    /** @param Collection<int, int> $warehouseIds @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    private function adjustmentRows(User $user, Collection $warehouseIds, array $filters): Collection
    {
        $query = StockAdjustment::query()->with(['warehouse', 'creator', 'approver', 'items'])->where('company_id', $user->company_id)->whereIn('warehouse_id', $warehouseIds);
        $this->dateRange($query, $filters, 'created_at');

        return $query->latest()->limit(self::ROW_LIMIT)->get()->map(fn (StockAdjustment $row) => [
            'adjustment' => $row->adjustment_number,
            'location' => $row->warehouse?->name,
            'type' => $row->adjustment_type,
            'reason' => $row->reason,
            'status' => $row->status,
            'items' => $row->items->count(),
            'created_by' => $row->creator?->name,
            'approved_by' => $row->approver?->name,
        ]);
    }

    /** @param Collection<int, int> $warehouseIds @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    private function countRows(User $user, Collection $warehouseIds, ?int $productId, array $filters): Collection
    {
        return InventoryStockCountItem::query()->with(['count.warehouse', 'product'])->whereHas('count', function (Builder $count) use ($user, $warehouseIds, $filters): void {
            $count->where('company_id', $user->company_id)->whereIn('warehouse_id', $warehouseIds);
            $this->dateRange($count, $filters, 'created_at');
        })->whereNotNull('variance_quantity')->when($productId, fn ($query) => $query->where('product_id', $productId))->limit(self::ROW_LIMIT)->get()->map(function (InventoryStockCountItem $row): array {
            $unitCostMinor = (int) round((float) $row->unit_cost * 100);
            $impactMinor = (int) round((float) $row->variance_quantity * $unitCostMinor);

            return [
                'count' => $row->count?->count_number,
                'location' => $row->count?->warehouse?->name,
                'product' => $row->product?->name,
                'expected' => (string) $row->system_quantity,
                'counted' => (string) $row->counted_quantity,
                'variance' => (string) $row->variance_quantity,
                'value_impact_minor' => $impactMinor,
                'value_impact' => number_format($impactMinor / 100, 2, '.', ''),
                'status' => $row->count?->status?->value,
            ];
        });
    }

    /** @param Collection<int, int> $warehouseIds @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    private function batchRows(User $user, Collection $warehouseIds, ?int $productId, array $filters, bool $expiryOnly): Collection
    {
        $query = InventoryBatch::query()->with(['product', 'warehouse', 'location'])->where('company_id', $user->company_id)->whereIn('warehouse_id', $warehouseIds)->when($productId, fn ($builder) => $builder->where('product_id', $productId))->when($expiryOnly, fn ($builder) => $builder->whereNotNull('expires_at'));
        $this->dateRange($query, $filters, 'created_at');

        return $query->orderBy('expires_at')->limit(self::ROW_LIMIT)->get()->map(fn (InventoryBatch $row) => [
            'product' => $row->product?->name,
            'sku' => $row->product?->sku,
            'batch' => $row->batch_number,
            'location' => $row->warehouse?->name,
            'quantity' => (string) $row->quantity_available,
            'manufactured' => $row->manufactured_at?->toDateString(),
            'expiry' => $row->expires_at?->toDateString(),
            'status' => $row->expires_at?->isPast() ? 'expired' : $row->status,
        ]);
    }

    /** @param Collection<int, int> $warehouseIds @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    private function serialRows(User $user, Collection $warehouseIds, ?int $productId, array $filters): Collection
    {
        $query = $this->locations->scopeSerials(InventorySerialNumber::query()->with(['product', 'warehouse', 'location']), $user)->when($productId, fn ($builder) => $builder->where('product_id', $productId));
        $this->dateRange($query, $filters, 'created_at');

        return $query->limit(self::ROW_LIMIT)->get()->map(fn (InventorySerialNumber $row) => [
            'serial' => $row->serial_number,
            'product' => $row->product?->name,
            'sku' => $row->product?->sku,
            'location' => $row->warehouse?->name,
            'bin' => $row->location?->code,
            'status' => $row->status,
        ]);
    }

    /** @param Collection<int, int> $warehouseIds @return Collection<int, array<string, mixed>> */
    private function reorderRows(User $user, Collection $warehouseIds, ?int $productId): Collection
    {
        return ReorderRule::query()->with(['product', 'warehouse', 'location'])->where('company_id', $user->company_id)->whereIn('warehouse_id', $warehouseIds)->when($productId, fn ($query) => $query->where('product_id', $productId))->limit(self::ROW_LIMIT)->get()->map(fn (ReorderRule $row) => [
            'product' => $row->product?->name,
            'location' => $row->warehouse?->name,
            'bin' => $row->location?->code,
            'minimum' => (string) $row->minimum_stock,
            'reorder_level' => (string) $row->reorder_point,
            'safety_stock' => (string) $row->safety_stock,
            'reorder_quantity' => (string) $row->reorder_quantity,
            'maximum' => (string) $row->maximum_stock,
            'active' => $row->is_active ? 'Yes' : 'No',
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function dateRange(Builder $query, array $filters, string $column): void
    {
        $query
            ->when(filled($filters['date_from'] ?? null), fn (Builder $builder) => $builder->whereDate($column, '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $builder) => $builder->whereDate($column, '<=', $filters['date_to']));
    }

    /** @param array<string, mixed> $filters @return Collection<int, int> */
    private function warehouseIds(User $user, array $filters): Collection
    {
        $warehouses = $this->locations->accessibleWarehouses($user, false);
        if (filled($filters['warehouse_id'] ?? null)) {
            $id = (int) $filters['warehouse_id'];
            abort_unless($warehouses->contains('id', $id), 403);

            return collect([$id]);
        }

        return $warehouses->pluck('id');
    }
}
