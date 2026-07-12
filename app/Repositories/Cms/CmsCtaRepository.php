<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsCtaBlock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CmsCtaRepository
{
    /** @param array<string, mixed> $filters */ public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator { return CmsCtaBlock::query()->where('company_id', $companyId)->when(($filters['trashed'] ?? null) === 'with', fn ($q) => $q->withTrashed())->orderBy('location')->orderBy('sort_order')->paginate(15)->withQueryString(); }
    public function findForCompany(int $companyId, int $id, bool $withTrashed = false): CmsCtaBlock { return CmsCtaBlock::query()->where('company_id', $companyId)->when($withTrashed, fn ($q) => $q->withTrashed())->findOrFail($id); }
}
