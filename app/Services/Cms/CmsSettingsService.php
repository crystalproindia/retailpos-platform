<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsFooterProfile;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsSocialLink;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;

class CmsSettingsService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function ensureDefaultSettings(int $companyId): void
    {
        collect(config('cms.settings'))->each(function (array $definition, string $key) use ($companyId): void {
            CmsSetting::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'key' => $key,
                ],
                [
                    'group' => $definition['group'] ?? 'general',
                    'label' => $definition['label'],
                    'value_type' => $definition['type'],
                    'is_public' => $definition['is_public'] ?? true,
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return Collection<int, CmsSetting>
     */
    public function updateSettings(User $user, array $values): Collection
    {
        $settings = collect(config('cms.settings'))->map(function (array $definition, string $key) use ($user, $values): CmsSetting {
            $isMedia = $definition['type'] === 'media';

            return CmsSetting::updateOrCreate(
                [
                    'company_id' => $user->company_id,
                    'key' => $key,
                ],
                [
                    'group' => $definition['group'] ?? 'general',
                    'label' => $definition['label'],
                    'media_id' => $isMedia ? ($values[$key] ?? null) : null,
                    'value' => $isMedia ? null : ($values[$key] ?? null),
                    'value_type' => $definition['type'],
                    'is_public' => $definition['is_public'] ?? true,
                ],
            );
        })->values();

        $this->auditLogger->record('cms.settings.updated', null, 'CMS settings updated', [
            'company_id' => $user->company_id,
        ]);

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFooter(CmsFooterProfile $footer, User $user, array $data): CmsFooterProfile
    {
        $footer->update($data);

        $this->auditLogger->record('cms.footer.updated', $footer, 'CMS footer updated');

        return $footer;
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     */
    public function replaceSocialLinks(User $user, array $links): void
    {
        CmsSocialLink::query()->where('company_id', $user->company_id)->delete();

        collect($links)
            ->filter(fn (array $link): bool => filled($link['platform'] ?? null) && filled($link['url'] ?? null))
            ->each(fn (array $link, int $index) => CmsSocialLink::create([
                'company_id' => $user->company_id,
                'platform' => $link['platform'],
                'url' => $link['url'],
                'icon' => $link['icon'] ?? null,
                'is_enabled' => (bool) ($link['is_enabled'] ?? true),
                'sort_order' => $link['sort_order'] ?? ($index + 1),
            ]));

        $this->auditLogger->record('cms.social_links.updated', null, 'CMS social links updated', [
            'company_id' => $user->company_id,
        ]);
    }
}
