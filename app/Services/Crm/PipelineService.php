<?php

namespace App\Services\Crm;

use App\Events\Domain\Crm\LeadStatusChanged;
use App\Events\Domain\Crm\PipelineStageChanged;
use App\Enums\Crm\ActivityType;
use App\Enums\Crm\PipelineStage;
use App\Models\Crm\CrmActivity;
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
        private readonly PipelineStageService $stageService,
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

        return $this->apply($lead, $status, $user, $this->stageService->stageForStatus($status), true);
    }

    public function move(CrmLead $lead, PipelineStage $targetStage, User $user): CrmLead
    {
        return $this->apply(
            $lead,
            $this->stageService->statusFor($lead->company_id, $targetStage),
            $user,
            $targetStage,
            false,
        );
    }

    private function apply(CrmLead $lead, CrmLeadStatus $status, User $user, PipelineStage $targetStage, bool $dispatchLegacyEvent): CrmLead
    {
        $lead->loadMissing(['status', 'latestProforma', 'latestQuotation', 'latestDemoSchedule']);
        $fromStatusId = $lead->status_id;
        $fromStage = $this->stageService->stageFor($lead);

        if ($fromStatusId === $status->id) {
            return $lead->refresh()->load('status');
        }

        $changes = ['status_id' => $status->id];
        if ($targetStage === PipelineStage::Won && ! $lead->won_at) {
            $changes['won_at'] = now();
        }
        if ($targetStage === PipelineStage::Lost && ! $lead->lost_at) {
            $changes['lost_at'] = now();
        }
        $lead->update($changes);

        CrmActivity::create([
            'company_id' => $lead->company_id,
            'crm_lead_id' => $lead->id,
            'crm_company_id' => $lead->crm_company_id,
            'crm_contact_id' => $lead->crm_contact_id,
            'assigned_user_id' => $lead->assigned_user_id,
            'created_by' => $user->id,
            'type' => ActivityType::Note,
            'subject' => "Pipeline moved from {$fromStage->label()} to {$targetStage->label()}",
            'description' => 'Pipeline stage updated from the sales pipeline.',
            'scheduled_at' => now(),
            'completed_at' => now(),
            'priority' => $lead->priority,
        ]);

        $this->auditLogger->record('crm.pipeline.transitioned', $lead, "Pipeline moved from {$fromStage->label()} to {$targetStage->label()}", [
            'from_status_id' => $fromStatusId,
            'to_status_id' => $status->id,
            'from_stage' => $fromStage->value,
            'to_stage' => $targetStage->value,
            'changed_by' => $user->id,
        ]);

        $payload = [
            'lead_id' => $lead->id,
            'lead_title' => $lead->title,
            'business_name' => $lead->business_name,
            'assigned_user_id' => $lead->assigned_user_id,
            'from_status_id' => $fromStatusId,
            'to_status_id' => $status->id,
            'to_status_name' => $status->name,
            'from_stage' => $fromStage->label(),
            'to_stage' => $targetStage->label(),
            'notify' => $targetStage->shouldNotify(),
        ];

        if ($dispatchLegacyEvent) {
            $this->domainEvents->dispatch(new LeadStatusChanged(
                companyId: $lead->company_id,
                actorId: $user->id,
                aggregateType: CrmLead::class,
                aggregateId: $lead->id,
                payload: $payload,
            ));
        } else {
            $this->domainEvents->dispatch(new PipelineStageChanged(
                companyId: $lead->company_id,
                actorId: $user->id,
                aggregateType: CrmLead::class,
                aggregateId: $lead->id,
                payload: $payload,
            ));
        }

        return $lead->refresh()->load('status');
    }
}
