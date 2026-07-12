<?php

namespace App\Repositories\Promotions;

use App\Models\Promotions\PromotionRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PromotionRuleRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateForCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return PromotionRule::query()->with(['campaign', 'actions', 'coupons'])->where('company_id', $companyId)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['promotion_type'] ?? null, fn ($query, $type) => $query->where('promotion_type', $type))
            ->when(($filters['trashed'] ?? false), fn ($query) => $query->onlyTrashed())
            ->orderByDesc('priority')->latest()->paginate($perPage)->withQueryString();
    }

    public function findForCompany(int $companyId, int $id, bool $withTrashed = false): PromotionRule
    {
        return $this->baseQuery($companyId)->when($withTrashed, fn ($query) => $query->withTrashed())->findOrFail($id);
    }

    /** @return Collection<int, PromotionRule> */
    public function activeForCart(int $companyId): Collection
    {
        return $this->baseQuery($companyId)->where('status', 'active')->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->orderByDesc('priority')->get();
    }

    private function baseQuery(int $companyId)
    {
        return PromotionRule::query()->with([
            'campaign', 'conditions', 'actions.freeProduct', 'productTargets', 'categoryTargets', 'brandTargets', 'variantTargets', 'branchTargets', 'channelTargets', 'coupons',
        ])->where('company_id', $companyId);
    }
}
