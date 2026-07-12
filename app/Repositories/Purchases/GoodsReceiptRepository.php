<?php

namespace App\Repositories\Purchases;

use App\Models\Purchases\GoodsReceipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GoodsReceiptRepository
{
    /**
     * @return LengthAwarePaginator<int, GoodsReceipt>
     */
    public function paginateForCompany(int $companyId): LengthAwarePaginator
    {
        return GoodsReceipt::query()
            ->with(['supplier', 'warehouse', 'purchaseOrder'])
            ->where('company_id', $companyId)
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function findForCompany(int $companyId, int $receiptId): GoodsReceipt
    {
        return GoodsReceipt::query()
            ->with(['supplier', 'warehouse', 'purchaseOrder.items', 'items.product', 'items.stockLocation'])
            ->where('company_id', $companyId)
            ->findOrFail($receiptId);
    }
}
