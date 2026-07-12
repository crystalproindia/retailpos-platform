<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsClientLogo;
use App\Models\User;
use App\Services\AuditLogger;

class CmsClientLogoService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly CmsProEventService $events) {}
    /** @param array<string, mixed> $data */ public function create(User $user, array $data): CmsClientLogo { $logo = CmsClientLogo::create($data + ['company_id' => $user->company_id]); $this->auditLogger->record('cms.client_logo.created', $logo, 'Client logo created'); $this->events->dispatch('cms.client_logo.created', $user, $logo, ['name' => $logo->name]); return $logo; }
    /** @param array<string, mixed> $data */ public function update(CmsClientLogo $logo, User $user, array $data): CmsClientLogo { $logo->update($data); $this->auditLogger->record('cms.client_logo.updated', $logo, 'Client logo updated'); $this->events->dispatch('cms.client_logo.updated', $user, $logo, ['name' => $logo->name]); return $logo->refresh(); }
    public function delete(CmsClientLogo $logo): void { $logo->delete(); $this->auditLogger->record('cms.client_logo.deleted', $logo, 'Client logo deleted'); }
    public function restore(CmsClientLogo $logo): CmsClientLogo { $logo->restore(); $this->auditLogger->record('cms.client_logo.restored', $logo, 'Client logo restored'); return $logo->refresh(); }
}
