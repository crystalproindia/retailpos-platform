<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsSetting;
use Illuminate\Support\Collection;

class CmsBrandingRepository
{
    /** @return Collection<string, CmsSetting> */
    public function forCompany(int $companyId): Collection
    {
        return CmsSetting::query()->where('company_id', $companyId)->whereIn('key', array_keys(config('cms.branding_settings')))->get()->keyBy('key');
    }
}
