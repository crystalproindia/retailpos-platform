<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsCtaBlock;
use App\Models\User;
use App\Services\AuditLogger;

class CmsCtaService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly CmsProEventService $events) {}
    /** @param array<string, mixed> $data */ public function create(User $user, array $data): CmsCtaBlock { $item = CmsCtaBlock::create($data + ['company_id' => $user->company_id]); $this->auditLogger->record('cms.cta.created', $item, 'CTA block created'); $this->events->dispatch('cms.cta.updated', $user, $item, ['title' => $item->title]); return $item; }
    /** @param array<string, mixed> $data */ public function update(CmsCtaBlock $item, User $user, array $data): CmsCtaBlock { $item->update($data); $this->auditLogger->record('cms.cta.updated', $item, 'CTA block updated'); $this->events->dispatch('cms.cta.updated', $user, $item, ['title' => $item->title]); return $item->refresh(); }
    public function delete(CmsCtaBlock $item): void { $item->delete(); $this->auditLogger->record('cms.cta.deleted', $item, 'CTA block deleted'); }
    public function restore(CmsCtaBlock $item): CmsCtaBlock { $item->restore(); $this->auditLogger->record('cms.cta.restored', $item, 'CTA block restored'); return $item->refresh(); }
}
