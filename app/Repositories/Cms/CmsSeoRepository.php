<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsBrokenLink;
use App\Models\Cms\CmsRedirect;
use App\Models\Cms\CmsSeoSetting;
use Illuminate\Support\Collection;

class CmsSeoRepository
{
    public function settingsForCompany(int $companyId): CmsSeoSetting
    {
        return CmsSeoSetting::firstOrCreate(['company_id' => $companyId]);
    }

    /**
     * @return Collection<int, CmsRedirect>
     */
    public function redirectsForCompany(int $companyId): Collection
    {
        return CmsRedirect::query()
            ->where('company_id', $companyId)
            ->orderBy('source_url')
            ->get();
    }

    /**
     * @return Collection<int, CmsBrokenLink>
     */
    public function brokenLinksForCompany(int $companyId): Collection
    {
        return CmsBrokenLink::query()
            ->where('company_id', $companyId)
            ->latest()
            ->limit(12)
            ->get();
    }
}
