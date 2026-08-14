<?php

namespace App\Repositories\Purchases;

use App\Models\Purchases\GoodsReceipt;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use App\Services\Purchases\PurchaseAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GoodsReceiptRepository
{
    public function __construct(private readonly OutletAccessService $outlets, private readonly PurchaseAccessService $access) {}

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

    /** @return LengthAwarePaginator<int, GoodsReceipt> */
    public function paginateForUser(User $user): LengthAwarePaginator
    {
        $outletIds = $this->outlets->accessibleOutlets($user)->pluck('id');

        return GoodsReceipt::query()
            ->with(['supplier', 'warehouse', 'purchaseOrder'])
            ->where('company_id', $user->company_id)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhereIn('branch_id', $outletIds))
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

    public function paginateForAuthorizedUser(User $user): LengthAwarePaginator
    {
        return $this->access->scope(GoodsReceipt::query()->with(['supplier', 'warehouse', 'purchaseOrder']), $user)->latest()->paginate(15)->withQueryString();
    }

    public function findForUser(User $user, int $receiptId): GoodsReceipt
    {
        return $this->access->scope(GoodsReceipt::query()->with(['supplier', 'warehouse', 'purchaseOrder.items', 'items.product', 'items.stockLocation', 'items.inventoryBatch']), $user)->findOrFail($receiptId);
    }
}
