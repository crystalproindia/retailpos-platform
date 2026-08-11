<?php

namespace App\Repositories\Pos;

use App\Models\Inventory\Product;
use Illuminate\Support\Collection;

class PosCatalogRepository
{
    /** @return Collection<int, Product> */
    public function search(int $companyId, ?int $branchId, ?string $term = null, ?int $warehouseId = null, ?int $stockLocationId = null): Collection
    {
        return Product::query()
            ->with(['category', 'brand'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('status', Product::STATUS_ACTIVE)
            ->when($term, function ($query, string $term): void {
                $query->where(fn ($match) => $match->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")->orWhere('barcode', 'like', "%{$term}%"));
            })
            ->when($branchId, fn ($query) => $query->with(['stockLevels' => fn ($stock) => $stock->where('branch_id', $branchId)->when($warehouseId, fn ($scope) => $scope->where('warehouse_id', $warehouseId))->when($stockLocationId, fn ($scope) => $scope->where('stock_location_id', $stockLocationId))]), fn ($query) => $query->with('stockLevels'))
            ->orderBy('name')
            ->limit(40)
            ->get()
            ->filter(fn (Product $product) => ! $product->track_inventory || $product->allow_negative_stock || $product->stockLevels->sum('quantity_available') > 0)
            ->values();
    }

    public function findSaleable(int $companyId, int $productId): Product
    {
        return Product::query()->with(['category', 'taxRate', 'unit'])->where('company_id', $companyId)->where('is_active', true)->where('status', Product::STATUS_ACTIVE)->findOrFail($productId);
    }

    public function findByBarcodeOrSku(int $companyId, ?int $branchId, string $code, ?int $warehouseId = null, ?int $stockLocationId = null): ?Product
    {
        return Product::query()
            ->with(['category', 'brand'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('status', Product::STATUS_ACTIVE)
            ->where(fn ($query) => $query->where('barcode', $code)->orWhere('sku', $code))
            ->when($branchId, fn ($query) => $query->with(['stockLevels' => fn ($stock) => $stock->where('branch_id', $branchId)->when($warehouseId, fn ($scope) => $scope->where('warehouse_id', $warehouseId))->when($stockLocationId, fn ($scope) => $scope->where('stock_location_id', $stockLocationId))]), fn ($query) => $query->with('stockLevels'))
            ->orderByRaw('case when barcode = ? then 0 else 1 end', [$code])
            ->first();
    }
}
