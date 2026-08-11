<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryStockCountItem;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryDashboardService
{
    public function __construct(private readonly InventoryLocationAccessService $locations) {}

    /**
     * @return array<string, mixed>
     */
    public function metrics(User $user, array $filters = []): array
    {
        $companyId = $user->company_id;
        $warehouseIds = $this->locations->accessibleWarehouses($user, false)->pluck('id');
        if (! empty($filters['warehouse_id'])) {
            abort_unless($warehouseIds->contains((int) $filters['warehouse_id']), 403);
            $warehouseIds = collect([(int) $filters['warehouse_id']]);
        }
        $stockLevels = StockLevel::query()->where('company_id', $companyId)->whereIn('warehouse_id', $warehouseIds);
        $transfers = StockTransfer::query()->where('company_id', $companyId)->where(fn ($query) => $query->whereIn('source_warehouse_id', $warehouseIds)->orWhereIn('destination_warehouse_id', $warehouseIds));
        $inventoryValueMinor = (int) StockLevel::query()
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->where('stock_levels.company_id', $companyId)
            ->whereIn('stock_levels.warehouse_id', $warehouseIds)
            ->selectRaw('COALESCE(ROUND(SUM(stock_levels.quantity_on_hand * COALESCE(products.cost_price, products.purchase_price, 0) * 100), 0), 0) as value_minor')
            ->value('value_minor');

        return [
            'cards' => [
                ['label' => 'Products', 'value' => Product::query()->where('company_id', $companyId)->count(), 'tone' => 'neutral'],
                ['label' => 'Active SKUs', 'value' => Product::query()->where('company_id', $companyId)->where('is_active', true)->count(), 'tone' => 'success'],
                ['label' => 'Low Stock', 'value' => (clone $stockLevels)->whereColumn('quantity_available', '<=', 'reorder_point')->where('quantity_available', '>', 0)->count(), 'tone' => 'warning'],
                ['label' => 'Out of Stock', 'value' => (clone $stockLevels)->where('quantity_available', '<=', 0)->count(), 'tone' => 'danger'],
                ['label' => 'In Transit', 'value' => (float) StockTransferItem::query()->where('in_transit_quantity', '>', 0)->whereHas('transfer', fn ($query) => $query->where('company_id', $companyId)->where(fn ($scope) => $scope->whereIn('source_warehouse_id', $warehouseIds)->orWhereIn('destination_warehouse_id', $warehouseIds)))->sum('in_transit_quantity'), 'tone' => 'neutral'],
                ['label' => 'Awaiting Approval', 'value' => (clone $transfers)->whereIn('status', ['requested', 'pending_approval'])->count(), 'tone' => 'warning'],
                ['label' => 'Transfers Arriving', 'value' => (clone $transfers)->whereIn('status', ['in_transit', 'partially_received', 'discrepancy'])->count(), 'tone' => 'neutral'],
                ['label' => 'Expiring in 30 Days', 'value' => InventoryBatch::query()->where('company_id', $companyId)->whereIn('warehouse_id', $warehouseIds)->whereBetween('expires_at', [today(), today()->addDays(30)])->sum('quantity_available'), 'tone' => 'warning'],
                ['label' => 'Dead Stock Candidates', 'value' => (clone $stockLevels)->where('quantity_on_hand', '>', 0)->where(fn ($query) => $query->whereNull('last_stock_movement_at')->orWhere('last_stock_movement_at', '<', now()->subYear()))->count(), 'tone' => 'neutral'],
                ['label' => 'Count Discrepancies', 'value' => InventoryStockCountItem::query()->where('variance_quantity', '!=', 0)->whereHas('count', fn ($query) => $query->where('company_id', $companyId)->whereIn('warehouse_id', $warehouseIds)->where('status', '!=', 'posted'))->count(), 'tone' => 'danger'],
            ],
            'inventory_value_minor' => $inventoryValueMinor,
            'inventory_value' => $inventoryValueMinor / 100,
            'categories' => InventoryCategory::query()->where('company_id', $companyId)->count(),
            'brands' => InventoryBrand::query()->where('company_id', $companyId)->count(),
            'recentStock' => StockLevel::query()
                ->with(['product', 'warehouse'])
                ->where('company_id', $companyId)
                ->whereIn('warehouse_id', $warehouseIds)
                ->latest('last_stock_movement_at')
                ->limit(8)
                ->get(),
            'stockByWarehouse' => StockLevel::query()
                ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
                ->where('stock_levels.company_id', $companyId)
                ->whereIn('stock_levels.warehouse_id', $warehouseIds)
                ->groupBy('warehouses.id', 'warehouses.name')
                ->orderBy('warehouses.name')
                ->get(['warehouses.name', DB::raw('SUM(stock_levels.quantity_available) as quantity')]),
        ];
    }
}
