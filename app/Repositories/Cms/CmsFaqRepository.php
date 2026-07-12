<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsFaq;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CmsFaqRepository
{
    /** @param array<string, mixed> $filters */ public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator { return CmsFaq::query()->where('company_id', $companyId)->when($filters['search'] ?? null, fn ($q, $search) => $q->where('question', 'like', "%{$search}%"))->when(($filters['trashed'] ?? null) === 'with', fn ($q) => $q->withTrashed())->orderBy('sort_order')->paginate(20)->withQueryString(); }
    public function findForCompany(int $companyId, int $id, bool $withTrashed = false): CmsFaq { return CmsFaq::query()->where('company_id', $companyId)->when($withTrashed, fn ($q) => $q->withTrashed())->findOrFail($id); }
}
