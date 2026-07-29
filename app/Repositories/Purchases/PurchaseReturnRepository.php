<?php

namespace App\Repositories\Purchases;

use App\Models\Purchases\PurchaseReturn;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseReturnRepository
{
    public function __construct(private readonly OutletAccessService $outlets) {}

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

    /** @return LengthAwarePaginator<int, PurchaseReturn> */
    public function paginateForUser(User $user): LengthAwarePaginator
    {
        return $this->queryForUser($user)
            ->with(['supplier', 'warehouse', 'goodsReceipt'])
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

    public function findForUser(User $user, int $returnId): PurchaseReturn
    {
        return $this->queryForUser($user)
            ->with(['supplier', 'warehouse', 'goodsReceipt', 'items.product', 'items.stockLocation'])
            ->findOrFail($returnId);
    }

    private function queryForUser(User $user)
    {
        $outletIds = $this->outlets->accessibleOutlets($user)->pluck('id');

        return PurchaseReturn::query()
            ->where('company_id', $user->company_id)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhereIn('branch_id', $outletIds));
    }
}
