<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsRedirect;
use App\Models\User;
use App\Services\AuditLogger;

class CmsRedirectService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}
    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): CmsRedirect { $redirect = CmsRedirect::create($data + ['company_id' => $user->company_id]); $this->auditLogger->record('cms.redirect.created', $redirect, 'CMS redirect created'); return $redirect; }
    /** @param array<string, mixed> $data */
    public function update(CmsRedirect $redirect, User $user, array $data): CmsRedirect { $redirect->update($data); $this->auditLogger->record('cms.redirect.updated', $redirect, 'CMS redirect updated'); return $redirect; }
    public function delete(CmsRedirect $redirect): void { $redirect->delete(); $this->auditLogger->record('cms.redirect.deleted', $redirect, 'CMS redirect deleted'); }
}
