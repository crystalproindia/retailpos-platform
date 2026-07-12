<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsThemeSetting;
use App\Models\User;
use App\Services\AuditLogger;

class CmsThemeService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly CmsProEventService $events) {}
    /** @param array<string, mixed> $data */ public function update(CmsThemeSetting $theme, User $user, array $data): CmsThemeSetting { $theme->update($data); $this->auditLogger->record('cms.theme.updated', $theme, 'CMS theme settings updated'); $this->events->dispatch('cms.theme.updated', $user, $theme); return $theme->refresh(); }
}
