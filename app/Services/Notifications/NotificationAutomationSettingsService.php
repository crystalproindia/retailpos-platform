<?php

namespace App\Services\Notifications;

use App\Models\Company;
use App\Models\NotificationAutomationSetting;

class NotificationAutomationSettingsService
{
    public function forCompany(Company|int $company): NotificationAutomationSetting
    {
        $company = $company instanceof Company ? $company : Company::query()->findOrFail($company);

        return NotificationAutomationSetting::query()->firstOrCreate(
            ['company_id' => $company->id],
            [
                'payment_before_due_days' => [3],
                'payment_overdue_days' => [1, 7, 30],
                'timezone' => $company->timezone ?: config('app.timezone'),
            ],
        );
    }
}
