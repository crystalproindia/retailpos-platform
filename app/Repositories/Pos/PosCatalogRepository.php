<?php

namespace App\Repositories\Pos;

use App\Models\Inventory\Product;
use Illuminate\Support\Collection;

class PosCatalogRepository
{
    /** @return Collection<int, Product> */
    public function search(int $companyId, ?int $branchId, ?string $term = null): Collection
    {
        return Product::query()
            ->with(['category', 'brand'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('status', Product::STATUS_ACTIVE)
            ->when($term, function ($query, string $term): void {
                $query->where(fn ($match) => $match->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")->orWhere('barcode', 'like', "%{$term}%"));
            })
            ->when($branchId, fn ($query) => $query->with(['stockLevels' => fn ($stock) => $stock->where('branch_id', $branchId)]), fn ($query) => $query->with('stockLevels'))
            ->orderBy('name')
            ->limit(40)
            ->get()
            ->filter(fn (Product $product) => ! $product->track_inventory || $product->allow_negative_stock || $product->stockLevels->sum('quantity_available') > 0)
            ->values();
    }

    public function findSaleable(int $companyId, int $productId): Product
    {
        return Product::query()->with('category')->where('company_id', $companyId)->where('is_active', true)->where('status', Product::STATUS_ACTIVE)->findOrFail($productId);
    }
}
