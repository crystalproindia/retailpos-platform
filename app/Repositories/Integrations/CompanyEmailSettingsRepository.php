<?php

namespace App\Repositories\Integrations;

use App\Models\CompanyEmailSetting;
use App\Models\User;

class CompanyEmailSettingsRepository
{
    public function forCompany(int $companyId): ?CompanyEmailSetting
    {
        return CompanyEmailSetting::query()->where('company_id', $companyId)->first();
    }

    /** @param array<string, mixed> $attributes */
    public function save(User $user, array $attributes): CompanyEmailSetting
    {
        return CompanyEmailSetting::query()->updateOrCreate(
            ['company_id' => $user->company_id],
            $attributes + ['updated_by' => $user->id],
        );
    }
}
