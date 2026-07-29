<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmOpportunityStageHistory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpportunityService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function create(CrmLead $lead, User $user, array $data): CrmOpportunity
    {
        $fingerprint = strtolower(trim(($data['title'] ?? $lead->title).'|'.($data['description'] ?? $lead->description ?? '')));
        if (CrmOpportunity::query()->where('company_id', $lead->company_id)->where('lead_id', $lead->id)->whereRaw('lower(title) = ?', [strtolower(trim((string) $data['title']))])->whereNotIn('stage', ['lost'])->exists()) {
            throw ValidationException::withMessages(['title' => 'An active opportunity with this title already exists for the lead.']);
        }

        return DB::transaction(function () use ($lead, $user, $data, $fingerprint): CrmOpportunity {
            $opportunity = CrmOpportunity::create(Arr::only($data, [
                'title', 'description', 'stage', 'expected_value', 'currency', 'probability_percentage', 'expected_close_date',
            ]) + [
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'crm_company_id' => $lead->crm_company_id,
                'crm_contact_id' => $lead->crm_contact_id,
                'assigned_user_id' => $data['assigned_user_id'] ?? $lead->assigned_user_id ?? $user->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            CrmOpportunityStageHistory::create([
                'opportunity_id' => $opportunity->id,
                'to_stage' => $opportunity->stage,
                'note' => 'Opportunity created.',
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);
            CrmActivity::create([
                'company_id' => $lead->company_id,
                'crm_lead_id' => $lead->id,
                'opportunity_id' => $opportunity->id,
                'assigned_user_id' => $opportunity->assigned_user_id,
                'created_by' => $user->id,
                'type' => ActivityType::Note,
                'subject' => 'Opportunity created: '.$opportunity->title,
                'scheduled_at' => now(),
                'completed_at' => now(),
                'completed_by' => $user->id,
                'follow_up_status' => 'completed',
                'priority' => $lead->priority ?? LeadPriority::Medium,
            ]);
            $this->auditLogger->record('crm.opportunity.created', $opportunity, 'Sales opportunity created', [
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'fingerprint' => hash('sha256', $fingerprint),
            ]);

            return $opportunity->load(['lead', 'assignedUser', 'stageHistory']);
        });
    }

    public function move(CrmOpportunity $opportunity, string $stage, User $user, ?string $note = null): CrmOpportunity
    {
        $allowed = ['qualified', 'demo_completed', 'proposal_required', 'quotation_sent', 'negotiation', 'won', 'lost'];
        if (! in_array($stage, $allowed, true)) {
            throw ValidationException::withMessages(['stage' => 'The selected opportunity stage is invalid.']);
        }
        if ($opportunity->stage === $stage) {
            return $opportunity;
        }
        if (in_array($opportunity->stage, ['won', 'lost'], true) && ! $user->can('sales.opportunities.close')) {
            throw ValidationException::withMessages(['stage' => 'A closed opportunity can only be reopened by an authorized user.']);
        }

        return DB::transaction(function () use ($opportunity, $stage, $user, $note): CrmOpportunity {
            $from = $opportunity->stage;
            $opportunity->update([
                'stage' => $stage,
                'won_at' => $stage === 'won' ? now() : null,
                'lost_at' => $stage === 'lost' ? now() : null,
                'loss_reason' => $stage === 'lost' ? ($note ?: $opportunity->loss_reason) : null,
                'updated_by' => $user->id,
            ]);
            CrmOpportunityStageHistory::create([
                'opportunity_id' => $opportunity->id,
                'from_stage' => $from,
                'to_stage' => $stage,
                'note' => $note,
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);
            $this->auditLogger->record('crm.opportunity.stage_changed', $opportunity, 'Opportunity stage changed', [
                'company_id' => $opportunity->company_id, 'from_stage' => $from, 'to_stage' => $stage,
            ]);

            return $opportunity->refresh();
        });
    }
}
