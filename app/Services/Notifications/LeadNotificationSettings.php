<?php

namespace App\Services\Notifications;

use App\Models\Setting;

class LeadNotificationSettings
{
    /**
     * @return array<string, mixed>
     */
    public function forCompany(int $companyId): array
    {
        $defaults = config('services.retailpos.lead_notifications', []);
        $overrides = Setting::query()
            ->where('company_id', $companyId)
            ->where('group', 'notifications')
            ->whereIn('key', array_keys($defaults))
            ->get()
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value['value'] ?? null])
            ->all();

        return array_merge($defaults, array_filter($overrides, fn (mixed $value): bool => $value !== null));
    }

    public function leadAlertsEnabled(int $companyId): bool
    {
        return (bool) ($this->forCompany($companyId)['lead_notifications_enabled'] ?? true);
    }

    public function emailEnabled(int $companyId): bool
    {
        return (bool) ($this->forCompany($companyId)['lead_email_notifications_enabled'] ?? false);
    }

    public function emailAddress(int $companyId): ?string
    {
        $email = $this->forCompany($companyId)['lead_notification_email'] ?? null;

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function notifyAdministrators(int $companyId): bool
    {
        return (bool) ($this->forCompany($companyId)['notify_admins_on_new_lead'] ?? true);
    }

    public function notifySales(int $companyId): bool
    {
        return (bool) ($this->forCompany($companyId)['notify_sales_on_new_lead'] ?? true);
    }

    public function followUpRemindersEnabled(int $companyId): bool
    {
        return (bool) ($this->forCompany($companyId)['followup_reminders_enabled'] ?? true);
    }
}
