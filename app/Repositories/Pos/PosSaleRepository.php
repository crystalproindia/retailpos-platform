<?php

namespace App\Repositories\Pos;

use App\Models\Pos\PosSale;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Support\Collection;

class PosSaleRepository
{
    public function __construct(private readonly OutletAccessService $outlets) {}

    public function findForUser(User $user, int $saleId): PosSale
    {
        return $this->queryForUser($user)->with(['items.product', 'payments', 'customer.groups.group', 'customer.loyaltyAccount', 'customer.insight'])->findOrFail($saleId);
    }

    /** @return Collection<int, PosSale> */
    public function heldForUser(User $user, ?string $search = null): Collection
    {
        return $this->queryForUser($user)
            ->with(['customer', 'items'])
            ->where('status', 'held')
            ->where('held_by', $user->id)
            ->when($search, fn ($query, string $search) => $query->where(fn ($held) => $held->where('sale_number', 'like', "%{$search}%")->orWhereHas('customer', fn ($customer) => $customer->where('display_name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))))
            ->latest('held_at')
            ->get();
    }

    public function queryForUser(User $user)
    {
        $outletIds = $this->outlets->accessibleOutlets($user)->pluck('id');

        return PosSale::query()
            ->where('company_id', $user->company_id)
            // Legacy sales predate outlet ownership and retain their previous visibility policy.
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhereIn('branch_id', $outletIds));
    }
}
