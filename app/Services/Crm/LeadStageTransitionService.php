<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\PipelineStage;
use App\Events\Domain\Crm\LeadStatusChanged;
use App\Events\Domain\Crm\PipelineStageChanged;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStageHistory;
use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadStageTransitionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
        private readonly PipelineStageService $stageService,
        private readonly CrmLeadScoringService $leadScoring,
    ) {}

    /**
     * @param  array{lost_reason?: string|null, lost_note?: string|null, idempotency_key?: string|null}  $context
     */
    public function transition(CrmLead $lead, int $statusId, User $user, string $auditEvent = 'crm.lead.status_changed', bool $pipelineDomainEvent = false, array $context = []): CrmLead
    {
        return DB::transaction(function () use ($lead, $statusId, $user, $auditEvent, $pipelineDomainEvent, $context): CrmLead {
            $lead = CrmLead::query()->lockForUpdate()->with(['status', 'assignedUser'])->findOrFail($lead->id);
            $status = CrmLeadStatus::query()
                ->where('company_id', $lead->company_id)
                ->where('is_active', true)
                ->find($statusId);

            if (! $status) {
                throw ValidationException::withMessages([
                    'status_id' => 'The selected pipeline status is invalid for this company.',
                ]);
            }

            $idempotencyKey = $context['idempotency_key'] ?? null;
            if ($idempotencyKey) {
                $existing = CrmLeadStageHistory::query()
                    ->where('company_id', $lead->company_id)
                    ->where('lead_id', $lead->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    if ((int) $existing->to_status_id !== $statusId) {
                        throw ValidationException::withMessages([
                            'stage_transition_key' => 'This transition key was already used for a different pipeline transition.',
                        ]);
                    }

                    return $lead->refresh()->load('status');
                }
            }

            $fromStatus = $lead->status;
            $fromStage = $this->stageService->stageFor($lead);
            $toStage = $this->stageService->stageForStatus($status);

            if ($lead->status_id === $status->id) {
                return $lead->refresh()->load('status');
            }

            $changes = ['status_id' => $status->id];
            if ($toStage === PipelineStage::Won && ! $lead->won_at) {
                $changes['won_at'] = now();
                $changes['lost_at'] = null;
                $changes['lost_reason'] = null;
            }
            if ($toStage === PipelineStage::Lost && ! $lead->lost_at) {
                $changes['lost_at'] = now();
                $changes['won_at'] = null;
            }
            if ($toStage !== PipelineStage::Lost) {
                $changes['lost_reason'] = null;
            } elseif (array_key_exists('lost_reason', $context)) {
                $changes['lost_reason'] = $context['lost_reason'];
            }
            if (! $toStage->isTerminal()) {
                $changes['won_at'] = null;
                $changes['lost_at'] = null;
                $changes['lost_reason'] = null;
            }

            $lead->update($changes);

            CrmLeadStageHistory::create([
                'company_id' => $lead->company_id,
                'branch_id' => $lead->branch_id,
                'lead_id' => $lead->id,
                'from_status_id' => $fromStatus?->id,
                'to_status_id' => $status->id,
                'from_stage' => $fromStage->value,
                'to_stage' => $toStage->value,
                'event_type' => $fromStage->isTerminal() && ! $toStage->isTerminal()
                    ? 'reopened'
                    : ($toStage === PipelineStage::Lost ? 'marked_lost' : ($toStage === PipelineStage::Won ? 'marked_won' : 'stage_transition')),
                'idempotency_key' => $idempotencyKey,
                'priority_snapshot' => $lead->priority?->value ?? $lead->priority,
                'assigned_user_id' => $lead->assigned_user_id,
                'assigned_user_name_snapshot' => $lead->assignedUser?->name,
                'lost_reason_snapshot' => $toStage === PipelineStage::Lost ? ($context['lost_reason'] ?? $lead->lost_reason) : null,
                'lost_note_snapshot' => $toStage === PipelineStage::Lost ? ($context['lost_note'] ?? null) : null,
                'actor_id' => $user->id,
                'actor_name_snapshot' => $user->name,
                'occurred_at' => now(),
                'metadata' => ['pipeline_event' => $pipelineDomainEvent],
            ]);

            $description = $toStage === PipelineStage::Lost && filled($context['lost_note'] ?? null)
                ? (string) $context['lost_note']
                : ($pipelineDomainEvent ? 'Pipeline stage updated from the sales pipeline.' : 'CRM lead status updated.');

            CrmActivity::create([
                'company_id' => $lead->company_id,
                'crm_lead_id' => $lead->id,
                'crm_company_id' => $lead->crm_company_id,
                'crm_contact_id' => $lead->crm_contact_id,
                'assigned_user_id' => $lead->assigned_user_id,
                'created_by' => $user->id,
                'type' => ActivityType::Note,
                'subject' => "Pipeline moved from {$fromStage->label()} to {$toStage->label()}",
                'description' => $description,
                'scheduled_at' => now(),
                'completed_at' => now(),
                'priority' => $lead->priority,
            ]);

            $payload = [
                'lead_id' => $lead->id,
                'lead_title' => $lead->title,
                'business_name' => $lead->business_name,
                'assigned_user_id' => $lead->assigned_user_id,
                'from_status_id' => $fromStatus?->id,
                'to_status_id' => $status->id,
                'to_status_name' => $status->name,
                'from_stage' => $fromStage->label(),
                'to_stage' => $toStage->label(),
                'lost_reason' => $toStage === PipelineStage::Lost ? ($context['lost_reason'] ?? $lead->lost_reason) : null,
                'notify' => $toStage->shouldNotify(),
            ];

            $this->auditLogger->record(
                $auditEvent,
                $lead,
                "Pipeline moved from {$fromStage->label()} to {$toStage->label()}",
                $payload + ['changed_by' => $user->id],
            );

            $event = $pipelineDomainEvent ? new PipelineStageChanged(
                companyId: $lead->company_id, actorId: $user->id, aggregateType: CrmLead::class, aggregateId: $lead->id, payload: $payload,
            ) : new LeadStatusChanged(
                companyId: $lead->company_id, actorId: $user->id, aggregateType: CrmLead::class, aggregateId: $lead->id, payload: $payload,
            );
            $this->domainEvents->dispatch($event);
            $this->leadScoring->refresh($lead, $user);

            return $lead->refresh()->load('status');
        });
    }
}
