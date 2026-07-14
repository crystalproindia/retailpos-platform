<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsPageSection;
use Illuminate\Support\Collection;

class CmsPageSectionRepository
{
    /**
     * @return Collection<int, CmsPageSection>
     */
    public function forPage(int $companyId, int $pageId): Collection
    {
        return CmsPageSection::query()
            ->where('company_id', $companyId)
            ->where('page_id', $pageId)
            ->orderBy('sort_order')
            ->get();
    }

    public function findForPage(int $companyId, int $pageId, int $sectionId): CmsPageSection
    {
        return CmsPageSection::query()
            ->where('company_id', $companyId)
            ->where('page_id', $pageId)
            ->findOrFail($sectionId);
    }
}
