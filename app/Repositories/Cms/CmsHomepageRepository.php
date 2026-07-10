<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsHomepageSection;
use Illuminate\Support\Collection;

class CmsHomepageRepository
{
    /**
     * @return Collection<int, CmsHomepageSection>
     */
    public function sectionsForCompany(int $companyId): Collection
    {
        return CmsHomepageSection::query()
            ->with('items')
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->get();
    }

    public function findSection(int $companyId, string $key): ?CmsHomepageSection
    {
        return CmsHomepageSection::query()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->first();
    }
}
