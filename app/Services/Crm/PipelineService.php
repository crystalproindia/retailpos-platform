<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class PipelineService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function transition(CrmLead $lead, int $statusId, User $user): CrmLead
    {
        $status = CrmLeadStatus::query()
            ->where('company_id', $lead->company_id)
            ->where('is_active', true)
            ->find($statusId);

        if (! $status) {
            throw ValidationException::withMessages([
                'status_id' => 'The selected pipeline status is invalid for this company.',
            ]);
        }

        $fromStatusId = $lead->status_id;
        $lead->update(['status_id' => $status->id]);

        $this->auditLogger->record('crm.pipeline.transitioned', $lead, 'CRM pipeline status changed', [
            'from_status_id' => $fromStatusId,
            'to_status_id' => $status->id,
            'changed_by' => $user->id,
        ]);

        return $lead->refresh()->load('status');
    }
}
