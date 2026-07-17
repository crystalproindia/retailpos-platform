<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsContentPage;
use App\Models\Cms\CmsContentSection;
use App\Models\Cms\CmsNavigationItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CmsContentPageRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(int $companyId, array $filters = []): LengthAwarePaginator
    {
        return CmsContentPage::query()
            ->withCount('sections')
            ->where('company_id', $companyId)
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('page_key', 'like', "%{$search}%")->orWhere('route_path', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['page_type'] ?? null, fn ($query, string $type) => $query->where('page_type', $type))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();
    }

    public function find(int $companyId, int $pageId): CmsContentPage
    {
        return CmsContentPage::query()->with('sections')->where('company_id', $companyId)->findOrFail($pageId);
    }

    public function findSection(CmsContentPage $page, int $sectionId): \App\Models\Cms\CmsContentSection
    {
        return $page->sections()->findOrFail($sectionId);
    }

    /** @return array{pages: int, published: int, drafts: int, disabled_sections: int, active_navigation_items: int, recent: \Illuminate\Support\Collection<int, CmsContentPage>} */
    public function dashboard(int $companyId): array
    {
        $pages = CmsContentPage::query()->where('company_id', $companyId);

        return [
            'pages' => (clone $pages)->count(),
            'published' => (clone $pages)->where('status', CmsContentPage::STATUS_PUBLISHED)->count(),
            'drafts' => (clone $pages)->where('status', CmsContentPage::STATUS_DRAFT)->count(),
            'disabled_sections' => CmsContentSection::query()
                ->where('is_enabled', false)
                ->whereHas('page', fn ($query) => $query->where('company_id', $companyId))
                ->count(),
            'active_navigation_items' => CmsNavigationItem::query()
                ->where('company_id', $companyId)
                ->where('is_enabled', true)
                ->count(),
            'recent' => (clone $pages)->latest('updated_at')->limit(3)->get(),
        ];
    }
}
