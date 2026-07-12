<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsSetting;
use App\Models\User;
use App\Services\AuditLogger;

class CmsHeaderService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly CmsProEventService $events) {}
    /** @param array<string, mixed> $data */ public function update(User $user, array $data): void { foreach (config('cms.header_settings') as $key => $definition) CmsSetting::updateOrCreate(['company_id' => $user->company_id, 'key' => $key], ['label' => $definition['label'], 'value_type' => $definition['type'], 'media_id' => $definition['type'] === 'media' ? ($data[$key] ?? null) : null, 'value' => $definition['type'] === 'media' ? null : ($data[$key] ?? null)]); $this->auditLogger->record('cms.header.updated', null, 'CMS header settings updated', ['company_id' => $user->company_id]); $this->events->dispatch('cms.branding.updated', $user, null, ['area' => 'header']); }
}
