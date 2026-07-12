<?php

namespace App\Repositories\Promotions;

use App\Models\Promotions\PromotionCoupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PromotionCouponRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateForCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return PromotionCoupon::query()->with('rule')->where('company_id', $companyId)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('code', 'like', "%{$search}%"))
            ->when(array_key_exists('active', $filters) && $filters['active'] !== null, fn ($query) => $query->where('is_active', (bool) $filters['active']))
            ->when(($filters['trashed'] ?? false), fn ($query) => $query->onlyTrashed())
            ->latest()->paginate($perPage)->withQueryString();
    }

    public function findForCompany(int $companyId, int $id, bool $withTrashed = false): PromotionCoupon
    {
        return PromotionCoupon::query()->with('rule')->where('company_id', $companyId)->when($withTrashed, fn ($query) => $query->withTrashed())->findOrFail($id);
    }

    public function byCode(int $companyId, string $code): ?PromotionCoupon
    {
        return PromotionCoupon::query()->with('rule.actions')->where('company_id', $companyId)->whereRaw('upper(code) = ?', [strtoupper(trim($code))])->first();
    }
}
