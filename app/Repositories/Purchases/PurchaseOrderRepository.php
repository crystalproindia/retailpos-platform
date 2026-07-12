<?php

namespace App\Repositories\Purchases;

use App\Models\Purchases\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseOrderRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function paginateForCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'warehouse', 'items.product'])
            ->where('company_id', $companyId)
            ->when(($filters['search'] ?? null), fn ($query, $search) => $query->where('po_number', 'like', "%{$search}%"))
            ->when(($filters['status'] ?? null), fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['supplier_id'] ?? null), fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->when(($filters['warehouse_id'] ?? null), fn ($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForCompany(int $companyId, int $orderId): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'warehouse', 'purchaseRequest', 'items.product', 'items.supplierProduct', 'goodsReceipts.items'])
            ->where('company_id', $companyId)
            ->findOrFail($orderId);
    }
}
