<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsTrustMetric;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CmsTrustMetricRepository
{
    /** @param array<string, mixed> $filters */ public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator { return CmsTrustMetric::query()->where('company_id', $companyId)->when(($filters['trashed'] ?? null) === 'with', fn ($q) => $q->withTrashed())->orderBy('sort_order')->paginate(20)->withQueryString(); }
    public function findForCompany(int $companyId, int $id, bool $withTrashed = false): CmsTrustMetric { return CmsTrustMetric::query()->where('company_id', $companyId)->when($withTrashed, fn ($q) => $q->withTrashed())->findOrFail($id); }
}
