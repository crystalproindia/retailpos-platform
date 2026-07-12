<?php

namespace App\Repositories\Purchases;

use App\Models\Purchases\PurchaseReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseReturnRepository
{
    /**
     * @return LengthAwarePaginator<int, PurchaseReturn>
     */
    public function paginateForCompany(int $companyId): LengthAwarePaginator
    {
        return PurchaseReturn::query()
            ->with(['supplier', 'warehouse', 'goodsReceipt'])
            ->where('company_id', $companyId)
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function findForCompany(int $companyId, int $returnId): PurchaseReturn
    {
        return PurchaseReturn::query()
            ->with(['supplier', 'warehouse', 'goodsReceipt', 'items.product', 'items.stockLocation'])
            ->where('company_id', $companyId)
            ->findOrFail($returnId);
    }
}
