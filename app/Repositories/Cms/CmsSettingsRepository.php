<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsFooterProfile;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsSocialLink;
use Illuminate\Support\Collection;

class CmsSettingsRepository
{
    /**
     * @return Collection<string, CmsSetting>
     */
    public function settingsForCompany(int $companyId): Collection
    {
        return CmsSetting::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('key');
    }

    public function footerForCompany(int $companyId): CmsFooterProfile
    {
        return CmsFooterProfile::firstOrCreate(['company_id' => $companyId]);
    }

    /**
     * @return Collection<int, CmsSocialLink>
     */
    public function socialLinksForCompany(int $companyId): Collection
    {
        return CmsSocialLink::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->get();
    }
}
