<?php

namespace App\Services\Pos;

use App\Models\Customers\Customer;
use App\Models\Inventory\Product;
use App\Models\Pos\CustomerProductSummary;
use App\Models\Pos\PosProductPairSummary;
use Illuminate\Support\Collection;

class CustomerProductSuggestionService
{
    /** @return array<string, Collection<int, Product>> */
    public function suggestions(Customer $customer, ?int $branchId): array
    {
        $base = CustomerProductSummary::query()->with('product.category')->where('company_id', $customer->company_id)->where('customer_id', $customer->id);
        $regular = $this->saleable((clone $base)->orderByDesc('purchase_count')->orderByDesc('quantity_purchased')->limit(8)->get()->pluck('product')->filter(), $branchId);
        $recent = $this->saleable((clone $base)->orderByDesc('last_purchased_at')->limit(8)->get()->pluck('product')->filter(), $branchId);
        $frequent = $this->saleable((clone $base)->orderByDesc('quantity_purchased')->orderByDesc('purchase_count')->limit(8)->get()->pluck('product')->filter(), $branchId);
        $productIds = $regular->pluck('id');
        $addons = $this->saleable(PosProductPairSummary::query()->with('relatedProduct.category')->where('company_id', $customer->company_id)->whereIn('product_id', $productIds)->orderByDesc('co_purchase_count')->limit(8)->get()->pluck('relatedProduct')->filter(), $branchId);
        if ($addons->isEmpty() && $regular->isNotEmpty()) {
            $categoryIds = $regular->pluck('category_id')->filter()->unique();
            $addons = $this->saleable(Product::query()->with('category')->where('company_id', $customer->company_id)->where('is_active', true)->where('status', Product::STATUS_ACTIVE)->whereIn('category_id', $categoryIds)->whereNotIn('id', $regular->pluck('id'))->orderBy('name')->limit(6)->get(), $branchId);
        }

        return ['regular' => $regular, 'frequent' => $frequent, 'recent' => $recent, 'last' => $recent->take(4)->values(), 'addons' => $addons];
    }

    /** @param Collection<int, Product> $products @return Collection<int, Product> */
    private function saleable(Collection $products, ?int $branchId): Collection
    {
        return $products->filter(function (Product $product) use ($branchId): bool {
            if (! $product->is_active || $product->status !== Product::STATUS_ACTIVE) return false;
            if (! $product->track_inventory || $product->allow_negative_stock) return true;
            return $product->stockLevels()->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->sum('quantity_available') > 0;
        })->unique('id')->values();
    }
}
