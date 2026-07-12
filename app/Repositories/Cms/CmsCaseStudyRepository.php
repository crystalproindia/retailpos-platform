<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsCaseStudy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CmsCaseStudyRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator { return CmsCaseStudy::query()->with('featuredImageMedia')->where('company_id', $companyId)->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($n) => $n->where('title', 'like', "%{$search}%")->orWhere('client_name', 'like', "%{$search}%")))->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->when(($filters['trashed'] ?? null) === 'with', fn ($q) => $q->withTrashed())->orderBy('sort_order')->latest()->paginate(12)->withQueryString(); }
    public function findForCompany(int $companyId, int $id, bool $withTrashed = false): CmsCaseStudy { return CmsCaseStudy::query()->with(['sections.media', 'clientLogoMedia', 'featuredImageMedia'])->where('company_id', $companyId)->when($withTrashed, fn ($q) => $q->withTrashed())->findOrFail($id); }
    /** @return Collection<int, CmsCaseStudy> */ public function optionsForCompany(int $companyId): Collection { return CmsCaseStudy::query()->where('company_id', $companyId)->orderBy('title')->get(); }
}
