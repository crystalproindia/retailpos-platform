<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;

class WhiteLabelService
{
    private const GROUP = 'saas_white_label';

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** @return array<string, mixed> */
    public function values(Company $company): array
    {
        $stored = Setting::query()->where('company_id', $company->id)->where('group', self::GROUP)->get()
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value['value'] ?? null]);

        return [
            'display_name' => $stored->get('display_name') ?: $company->trade_name ?: $company->name,
            'logo_media_id' => $stored->get('logo_media_id'),
            'favicon_media_id' => $stored->get('favicon_media_id'),
            'primary_color' => $stored->get('primary_color') ?: '#0f172a',
            'secondary_color' => $stored->get('secondary_color') ?: '#0f766e',
            'support_email' => $stored->get('support_email') ?: $company->email,
            'email_sender_name' => $stored->get('email_sender_name') ?: $company->name,
            'show_powered_by' => $stored->has('show_powered_by') ? (bool) $stored->get('show_powered_by') : true,
            'custom_domain' => $stored->get('custom_domain'),
            'custom_domain_status' => $stored->get('custom_domain_status') ?: 'not_configured',
        ];
    }

    /** @param array<string, mixed> $values */
    public function update(Company $company, User $actor, array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['company_id' => $company->id, 'group' => self::GROUP, 'key' => $key],
                ['value' => ['value' => $value]],
            );
        }

        $this->audit->record('saas.white_label.updated', $company, 'White-label settings updated.', [
            'company_id' => $company->id,
            'keys' => array_keys($values),
            'actor_id' => $actor->id,
        ]);
    }
}
