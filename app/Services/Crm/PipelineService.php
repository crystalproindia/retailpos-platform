<?php

namespace App\Services\Crm;

use App\Events\Domain\Crm\LeadStatusChanged;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Validation\ValidationException;

class PipelineService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

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
        $this->domainEvents->dispatch(new LeadStatusChanged(
            companyId: $lead->company_id,
            actorId: $user->id,
            aggregateType: CrmLead::class,
            aggregateId: $lead->id,
            payload: [
                'lead_id' => $lead->id,
                'lead_title' => $lead->title,
                'business_name' => $lead->business_name,
                'assigned_user_id' => $lead->assigned_user_id,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $status->id,
                'to_status_name' => $status->name,
            ],
        ));

        return $lead->refresh()->load('status');
    }
}
