<?php

namespace App\Services\Crm;

use App\Enums\Crm\InvoiceReminderStage;
use App\Models\Company;
use App\Models\Crm\CrmInvoiceReminderRule;
use App\Models\Crm\CrmInvoiceReminderSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class InvoiceReminderSettingsService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function ensure(Company $company): CrmInvoiceReminderSetting
    {
        $setting = CrmInvoiceReminderSetting::query()->firstOrCreate(
            ['company_id' => $company->id],
            ['automatic_enabled' => false, 'minimum_cooldown_hours' => 24],
        );

        $this->ensureRules($setting);

        return $setting->fresh('rules');
    }

    public function find(Company|int $company): ?CrmInvoiceReminderSetting
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return CrmInvoiceReminderSetting::query()->with('rules')->where('company_id', $companyId)->first();
    }

    /** @param array{automatic_enabled:bool,minimum_cooldown_hours:int,rules:array<int,array<string,mixed>>} $data */
    public function update(Company $company, User $user, array $data): CrmInvoiceReminderSetting
    {
        return DB::transaction(function () use ($company, $user, $data): CrmInvoiceReminderSetting {
            $setting = $this->ensure($company);
            $setting->update([
                'automatic_enabled' => $data['automatic_enabled'],
                'minimum_cooldown_hours' => $data['minimum_cooldown_hours'],
                'updated_by' => $user->id,
            ]);

            foreach ($data['rules'] as $position => $rule) {
                CrmInvoiceReminderRule::query()
                    ->where('company_id', $company->id)
                    ->where('stage', $rule['stage'])
                    ->update([
                        'enabled' => $rule['enabled'],
                        'offset_days' => $rule['offset_days'],
                        'attach_pdf' => $rule['attach_pdf'],
                        'include_secure_link' => $rule['include_secure_link'],
                        'subject' => $rule['subject'],
                        'intro_message' => $rule['intro_message'],
                        'sort_order' => $position + 1,
                    ]);
            }

            $this->audit->record('crm.invoice_reminders.settings_updated', $setting, 'Invoice reminder settings updated', ['company_id' => $company->id]);

            return $setting->fresh('rules');
        });
    }

    public function restoreDefaults(Company $company, User $user): CrmInvoiceReminderSetting
    {
        return DB::transaction(function () use ($company, $user): CrmInvoiceReminderSetting {
            $setting = $this->ensure($company);
            $setting->update(['automatic_enabled' => false, 'minimum_cooldown_hours' => 24, 'updated_by' => $user->id]);

            foreach ($this->defaultRules() as $attributes) {
                CrmInvoiceReminderRule::query()->updateOrCreate(
                    ['company_id' => $company->id, 'stage' => $attributes['stage']],
                    $attributes + ['company_id' => $company->id, 'reminder_setting_id' => $setting->id],
                );
            }

            $this->audit->record('crm.invoice_reminders.defaults_restored', $setting, 'Invoice reminder defaults restored', ['company_id' => $company->id]);

            return $setting->fresh('rules');
        });
    }

    /** @return array<int, array{stage:string,enabled:bool,offset_days:int,attach_pdf:bool,include_secure_link:bool,subject:string,intro_message:string,sort_order:int}> */
    public function defaultRules(): array
    {
        return collect(InvoiceReminderStage::cases())
            ->filter(fn (InvoiceReminderStage $stage): bool => $stage->isAutomatic())
            ->values()
            ->map(fn (InvoiceReminderStage $stage, int $index): array => [
                'stage' => $stage->value,
                'enabled' => true,
                'offset_days' => $stage->defaultOffsetDays(),
                'attach_pdf' => true,
                'include_secure_link' => true,
                'subject' => $stage->defaultSubject(),
                'intro_message' => $stage->defaultIntro(),
                'sort_order' => $index + 1,
            ])
            ->all();
    }

    private function ensureRules(CrmInvoiceReminderSetting $setting): void
    {
        foreach ($this->defaultRules() as $attributes) {
            CrmInvoiceReminderRule::query()->firstOrCreate(
                ['company_id' => $setting->company_id, 'stage' => $attributes['stage']],
                $attributes + ['reminder_setting_id' => $setting->id],
            );
        }
    }
}
