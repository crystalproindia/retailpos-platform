<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsRedirect;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CmsMarketingRepository
{
    /** @param array<string, mixed> $filters */
    public function pages(int $companyId, string $kind, array $filters = []): LengthAwarePaginator
    {
        return CmsPage::query()->with('seo')->where('company_id', $companyId)
            ->when($kind === 'seo', fn ($query) => $query->where('page_type', 'seo'), fn ($query) => $query->whereIn('page_type', ['landing', 'product', 'industry', 'module', 'solution', 'location', 'comparison']))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('route_path', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['page_type'] ?? null, fn ($query, $type) => $query->where('page_type', $type))
            ->latest()->paginate(15)->withQueryString();
    }

    public function page(int $companyId, int $id): CmsPage { return CmsPage::query()->with(['seo', 'revisions.user'])->where('company_id', $companyId)->findOrFail($id); }

    /** @param array<string, mixed> $filters */
    public function articles(int $companyId, array $filters = []): LengthAwarePaginator
    {
        return CmsArticle::query()->where('company_id', $companyId)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('category', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(15)->withQueryString();
    }

    public function article(int $companyId, int $id): CmsArticle { return CmsArticle::query()->where('company_id', $companyId)->findOrFail($id); }
    public function redirects(int $companyId) { return CmsRedirect::query()->where('company_id', $companyId)->latest()->paginate(20); }
    public function redirect(int $companyId, int $id): CmsRedirect { return CmsRedirect::query()->where('company_id', $companyId)->findOrFail($id); }
}
