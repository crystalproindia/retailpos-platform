<?php

namespace App\Repositories\Pos;

use App\Models\Pos\PosSale;
use Illuminate\Support\Collection;

class PosSaleRepository
{
    public function findForCompany(int $companyId, int $saleId): PosSale
    {
        return PosSale::query()->with(['items.product', 'payments', 'customer.groups.group', 'customer.loyaltyAccount', 'customer.insight'])->where('company_id', $companyId)->findOrFail($saleId);
    }

    /** @return Collection<int, PosSale> */
    public function heldForUser(int $companyId, int $userId): Collection
    {
        return PosSale::query()->with('customer')->where('company_id', $companyId)->where('status', 'held')->where('held_by', $userId)->latest('held_at')->get();
    }
}
