<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPageSection;
use App\Models\User;
use App\Services\AuditLogger;

class CmsPageSectionService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(CmsPage $page, User $user, array $data): CmsPageSection
    {
        $section = $page->sections()->create($data + ['company_id' => $user->company_id]);

        $this->auditLogger->record('cms.page_section.created', $section, 'CMS page section created');

        return $section;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CmsPageSection $section, array $data): CmsPageSection
    {
        $section->update($data);

        $this->auditLogger->record('cms.page_section.updated', $section, 'CMS page section updated');

        return $section;
    }

    public function delete(CmsPageSection $section): void
    {
        $section->delete();

        $this->auditLogger->record('cms.page_section.deleted', $section, 'CMS page section deleted');
    }
}
