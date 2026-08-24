<?php

namespace App\Repositories\Pos;

use App\Models\Inventory\Product;
use App\Models\Pos\PosProductFavorite;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /** @return array<int, int> */
    public function favoriteIds(User $user, int $branchId): array
    {
        return PosProductFavorite::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $branchId)
            ->where('user_id', $user->id)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function toggleFavorite(User $user, int $branchId, int $productId): bool
    {
        Product::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('status', Product::STATUS_ACTIVE)
            ->findOrFail($productId);

        return DB::transaction(function () use ($user, $branchId, $productId): bool {
            $favorite = PosProductFavorite::query()
                ->where('company_id', $user->company_id)
                ->where('branch_id', $branchId)
                ->where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();

            if ($favorite) {
                $favorite->delete();

                return false;
            }

            PosProductFavorite::query()->create([
                'company_id' => $user->company_id,
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);

            return true;
        });
    }
}
