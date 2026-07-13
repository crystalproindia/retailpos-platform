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
    public function heldForUser(int $companyId, int $userId, ?string $search = null): Collection
    {
        return PosSale::query()
            ->with(['customer', 'items'])
            ->where('company_id', $companyId)
            ->where('status', 'held')
            ->where('held_by', $userId)
            ->when($search, fn ($query, string $search) => $query->where(fn ($held) => $held->where('sale_number', 'like', "%{$search}%")->orWhereHas('customer', fn ($customer) => $customer->where('display_name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))))
            ->latest('held_at')
            ->get();
    }
}
