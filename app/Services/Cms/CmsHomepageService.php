<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsHomepageSection;
use App\Models\User;
use App\Services\AuditLogger;

class CmsHomepageService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function ensureDefaultSections(int $companyId): void
    {
        collect(config('cms.homepage_sections'))->each(function (array $section, string $key) use ($companyId): void {
            CmsHomepageSection::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'key' => $key,
                ],
                [
                    'name' => $section['name'],
                    'sort_order' => $section['sort_order'],
                    'is_enabled' => true,
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSection(CmsHomepageSection $section, User $user, array $data): CmsHomepageSection
    {
        $section->update($data);

        $this->auditLogger->record('cms.homepage.updated', $section, 'CMS homepage section updated', [
            'section' => $section->key,
        ]);

        return $section;
    }
}
