<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsClientLogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CmsClientLogoRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator { return CmsClientLogo::query()->with('logoMedia')->where('company_id', $companyId)->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))->when(($filters['trashed'] ?? null) === 'with', fn ($q) => $q->withTrashed())->orderBy('sort_order')->latest()->paginate(15)->withQueryString(); }
    public function findForCompany(int $companyId, int $id, bool $withTrashed = false): CmsClientLogo { return CmsClientLogo::query()->where('company_id', $companyId)->when($withTrashed, fn ($q) => $q->withTrashed())->findOrFail($id); }
}
