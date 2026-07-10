<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CmsPageRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        return CmsPage::query()
            ->with(['seo', 'author'])
            ->where('company_id', $companyId)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(($filters['trashed'] ?? null) === 'with', fn ($query) => $query->withTrashed())
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function findForCompany(int $companyId, int $pageId, bool $withTrashed = false): CmsPage
    {
        return CmsPage::query()
            ->with(['seo', 'revisions.user'])
            ->where('company_id', $companyId)
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->findOrFail($pageId);
    }
}
