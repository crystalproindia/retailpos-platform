<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsRedirect;
use App\Models\Cms\CmsSeoSetting;
use App\Models\User;
use App\Services\AuditLogger;

class CmsSeoService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly CmsProEventService $events) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(CmsSeoSetting $settings, User $user, array $data): CmsSeoSetting
    {
        $settings->update($data);

        $this->auditLogger->record('cms.seo.updated', $settings, 'CMS SEO settings updated');
        $this->events->dispatch('cms.seo.updated', $user, $settings);

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRedirect(User $user, array $data): CmsRedirect
    {
        return CmsRedirect::create($data + ['company_id' => $user->company_id]);
    }
}
