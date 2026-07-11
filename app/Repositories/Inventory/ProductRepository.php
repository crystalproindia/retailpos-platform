<?php

namespace App\Repositories\Inventory;

use App\Models\Inventory\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateForCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category', 'brand', 'unit', 'stockLevels.warehouse'])
            ->where('company_id', $companyId)
            ->when(($filters['search'] ?? null), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('hsn_code', 'like', "%{$search}%");
                });
            })
            ->when(($filters['category_id'] ?? null), fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when(($filters['brand_id'] ?? null), fn ($query, $brandId) => $query->where('brand_id', $brandId))
            ->when(($filters['status'] ?? null), fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['trashed'] ?? null) === 'with', fn ($query) => $query->withTrashed())
            ->when(($filters['trashed'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForCompany(int $companyId, int $productId, bool $withTrashed = false): Product
    {
        return Product::query()
            ->with(['category', 'brand', 'unit', 'taxRate', 'parent', 'variants.attributeValues.attribute', 'stockLevels.warehouse', 'stockLevels.location', 'stockMovements.warehouse'])
            ->where('company_id', $companyId)
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->findOrFail($productId);
    }

    /**
     * @return Collection<int, Product>
     */
    public function activeForCompany(int $companyId): Collection
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
