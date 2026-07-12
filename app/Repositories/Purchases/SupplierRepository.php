<?php

namespace App\Repositories\Purchases;

use App\Models\Purchases\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SupplierRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        return Supplier::query()
            ->withCount(['products', 'purchaseOrders'])
            ->where('company_id', $companyId)
            ->when(($filters['search'] ?? null), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('gstin', 'like', "%{$search}%");
                });
            })
            ->when(($filters['supplier_type'] ?? null), fn ($query, $type) => $query->where('supplier_type', $type))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(($filters['rating'] ?? null) === 'high', fn ($query) => $query->where('rating', '>=', 80))
            ->when(($filters['rating'] ?? null) === 'low', fn ($query) => $query->where('rating', '<', 60))
            ->when(($filters['has_products'] ?? null), fn ($query) => $query->has('products'))
            ->when(($filters['trashed'] ?? null) === 'with', fn ($query) => $query->withTrashed())
            ->when(($filters['trashed'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function findForCompany(int $companyId, int $supplierId, bool $withTrashed = false): Supplier
    {
        return Supplier::query()
            ->with(['contacts', 'addresses', 'products.product', 'products.taxRate', 'scoreSnapshots', 'purchaseOrders.items', 'goodsReceipts.items', 'purchaseReturns.items'])
            ->where('company_id', $companyId)
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->findOrFail($supplierId);
    }

    /**
     * @return Collection<int, Supplier>
     */
    public function activeForCompany(int $companyId): Collection
    {
        return Supplier::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
