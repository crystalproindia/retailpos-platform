<?php

namespace App\Services\Crm;

use App\Enums\Crm\PipelineStage;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PipelineService
{
    public function __construct(
        private readonly PipelineStageService $stageService,
        private readonly LeadStageTransitionService $transitions,
    ) {}

    /** @param array{lost_reason?: string|null, lost_note?: string|null, stage_transition_key?: string|null} $context */
    public function transition(CrmLead $lead, int $statusId, User $user, array $context = []): CrmLead
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

        return $this->transitions->transition($lead, $status->id, $user, 'crm.pipeline.transitioned', false, [
            'lost_reason' => $context['lost_reason'] ?? null,
            'lost_note' => $context['lost_note'] ?? null,
            'idempotency_key' => $context['stage_transition_key'] ?? null,
        ]);
    }

    /** @param array{lost_reason?: string|null, lost_note?: string|null, stage_transition_key?: string|null} $context */
    public function move(CrmLead $lead, PipelineStage $targetStage, User $user, array $context = []): CrmLead
    {
        return $this->transitions->transition(
            $lead,
            $this->stageService->statusFor($lead->company_id, $targetStage)->id,
            $user,
            'crm.pipeline.transitioned',
            true,
            [
                'lost_reason' => $context['lost_reason'] ?? null,
                'lost_note' => $context['lost_note'] ?? null,
                'idempotency_key' => $context['stage_transition_key'] ?? null,
            ],
        );
    }
}
