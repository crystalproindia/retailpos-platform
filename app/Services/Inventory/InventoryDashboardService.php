<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\Product;
use App\Models\Inventory\ReorderSuggestion;
use App\Models\Inventory\SalesChannel;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;

class InventoryDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(int $companyId): array
    {
        $stockLevels = StockLevel::query()->where('company_id', $companyId);

        return [
            'cards' => [
                ['label' => 'Products', 'value' => Product::query()->where('company_id', $companyId)->count(), 'tone' => 'neutral'],
                ['label' => 'Active SKUs', 'value' => Product::query()->where('company_id', $companyId)->where('is_active', true)->count(), 'tone' => 'success'],
                ['label' => 'Low Stock', 'value' => (clone $stockLevels)->whereColumn('quantity_available', '<=', 'reorder_point')->where('quantity_available', '>', 0)->count(), 'tone' => 'warning'],
                ['label' => 'Out of Stock', 'value' => (clone $stockLevels)->where('quantity_available', '<=', 0)->count(), 'tone' => 'danger'],
                ['label' => 'Warehouses', 'value' => Warehouse::query()->where('company_id', $companyId)->count(), 'tone' => 'neutral'],
                ['label' => 'Channels', 'value' => SalesChannel::query()->where('company_id', $companyId)->count(), 'tone' => 'neutral'],
                ['label' => 'Pending Reorders', 'value' => ReorderSuggestion::query()->where('company_id', $companyId)->where('status', 'pending')->count(), 'tone' => 'warning'],
                ['label' => 'Draft Adjustments', 'value' => StockAdjustment::query()->where('company_id', $companyId)->where('status', 'draft')->count(), 'tone' => 'neutral'],
            ],
            'inventory_value' => (float) StockLevel::query()
                ->join('products', 'products.id', '=', 'stock_levels.product_id')
                ->where('stock_levels.company_id', $companyId)
                ->selectRaw('COALESCE(SUM(stock_levels.quantity_on_hand * COALESCE(products.cost_price, products.purchase_price, 0)), 0) as value')
                ->value('value'),
            'categories' => InventoryCategory::query()->where('company_id', $companyId)->count(),
            'brands' => InventoryBrand::query()->where('company_id', $companyId)->count(),
            'recentStock' => StockLevel::query()
                ->with(['product', 'warehouse'])
                ->where('company_id', $companyId)
                ->latest('last_stock_movement_at')
                ->limit(8)
                ->get(),
            'stockByWarehouse' => StockLevel::query()
                ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
                ->where('stock_levels.company_id', $companyId)
                ->groupBy('warehouses.id', 'warehouses.name')
                ->orderBy('warehouses.name')
                ->get(['warehouses.name', DB::raw('SUM(stock_levels.quantity_available) as quantity')]),
        ];
    }
}
