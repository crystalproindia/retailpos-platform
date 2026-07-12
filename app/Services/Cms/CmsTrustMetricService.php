<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsTrustMetric;
use App\Models\User;
use App\Services\AuditLogger;

class CmsTrustMetricService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly CmsProEventService $events) {}
    /** @param array<string, mixed> $data */ public function create(User $user, array $data): CmsTrustMetric { $item = CmsTrustMetric::create($data + ['company_id' => $user->company_id]); $this->auditLogger->record('cms.trust_metric.created', $item, 'Trust metric created'); $this->events->dispatch('cms.trust_metric.updated', $user, $item, ['label' => $item->label]); return $item; }
    /** @param array<string, mixed> $data */ public function update(CmsTrustMetric $item, User $user, array $data): CmsTrustMetric { $item->update($data); $this->auditLogger->record('cms.trust_metric.updated', $item, 'Trust metric updated'); $this->events->dispatch('cms.trust_metric.updated', $user, $item, ['label' => $item->label]); return $item->refresh(); }
    public function delete(CmsTrustMetric $item): void { $item->delete(); $this->auditLogger->record('cms.trust_metric.deleted', $item, 'Trust metric deleted'); }
    public function restore(CmsTrustMetric $item): CmsTrustMetric { $item->restore(); $this->auditLogger->record('cms.trust_metric.restored', $item, 'Trust metric restored'); return $item->refresh(); }
}
