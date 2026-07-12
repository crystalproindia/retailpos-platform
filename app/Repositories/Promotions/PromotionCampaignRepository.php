<?php

namespace App\Repositories\Promotions;

use App\Models\Promotions\PromotionCampaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PromotionCampaignRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateForCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return PromotionCampaign::query()->withCount('rules')->where('company_id', $companyId)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['trashed'] ?? false), fn ($query) => $query->onlyTrashed())
            ->orderByDesc('priority')->latest()->paginate($perPage)->withQueryString();
    }

    public function findForCompany(int $companyId, int $id, bool $withTrashed = false): PromotionCampaign
    {
        return PromotionCampaign::query()->with(['rules.actions'])->where('company_id', $companyId)->when($withTrashed, fn ($query) => $query->withTrashed())->findOrFail($id);
    }
}
