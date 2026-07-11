<?php

namespace App\Repositories\Inventory;

use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StockRepository
{
    public function level(int $companyId, int $warehouseId, ?int $locationId, int $productId): StockLevel
    {
        return StockLevel::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'stock_location_id' => $locationId,
                'product_id' => $productId,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_available' => 0,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function ledger(int $companyId, array $filters = []): LengthAwarePaginator
    {
        return StockMovement::query()
            ->with(['product', 'warehouse', 'location', 'creator'])
            ->where('company_id', $companyId)
            ->when(($filters['product_id'] ?? null), fn ($query, $productId) => $query->where('product_id', $productId))
            ->when(($filters['warehouse_id'] ?? null), fn ($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when(($filters['movement_type'] ?? null), fn ($query, $type) => $query->where('movement_type', $type))
            ->when(($filters['date_from'] ?? null), fn ($query, $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when(($filters['date_to'] ?? null), fn ($query, $date) => $query->whereDate('occurred_at', '<=', $date))
            ->latest('occurred_at')
            ->paginate(20)
            ->withQueryString();
    }
}
