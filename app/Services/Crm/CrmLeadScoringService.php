<?php

namespace App\Services\Crm;

use App\Data\Crm\CrmLeadScoreResult;
use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadScoreCategory;
use App\Enums\Crm\LeadScoreConfidence;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\ProformaStatus;
use App\Events\Domain\Crm\LeadScoreInsight;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadScore;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Database\Eloquent\Builder;

class CrmLeadScoringService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
        private readonly PipelineStageService $stages,
    ) {}

    public function refresh(CrmLead $lead, ?User $actor = null, bool $isManual = false): CrmLeadScoreResult
    {
        $lead = $this->leadForAnalysis($lead);
        $previous = $lead->leadScore;
        $result = $this->calculate($lead);

        $snapshot = CrmLeadScore::query()->updateOrCreate(
            ['lead_id' => $lead->id],
            [
                'company_id' => $lead->company_id,
                'score' => $result->score,
                'category' => $result->category,
                'confidence' => $result->confidence,
                'priority' => $result->priority,
                'next_best_action' => $result->nextBestAction,
                'reasons' => $result->reasons,
                'risks' => $result->risks,
                'opportunities' => $result->opportunities,
                'metadata' => $result->metadata,
                'analyzed_at' => now(),
                'created_by' => $actor?->id,
            ],
        );

        $lead->update(['lead_score' => $result->score]);

        if ($isManual && $actor) {
            $this->recordManualRefresh($lead, $actor, $result);
        }

        $this->dispatchMeaningfulInsight($lead, $previous, $snapshot, $result, $actor);

        return $result;
    }

    public function latestFor(CrmLead $lead): ?CrmLeadScore
    {
        return CrmLeadScore::query()
            ->where('company_id', $lead->company_id)
            ->where('lead_id', $lead->id)
            ->first();
    }

    public function resultFromSnapshot(CrmLeadScore $snapshot): CrmLeadScoreResult
    {
        return new CrmLeadScoreResult(
            score: $snapshot->score,
            category: $snapshot->category,
            confidence: $snapshot->confidence,
            priority: $snapshot->priority,
            nextBestAction: $snapshot->next_best_action,
            reasons: $snapshot->reasons ?? [],
            risks: $snapshot->risks ?? [],
            opportunities: $snapshot->opportunities ?? [],
            metadata: $snapshot->metadata ?? [],
        );
    }

    /**
     * @return array{hot_leads: \Illuminate\Support\Collection<int, CrmLeadScore>, at_risk_leads: \Illuminate\Support\Collection<int, CrmLeadScore>, hot_count: int, at_risk_count: int, follow_ups_today: int, overdue_follow_ups: int, partial_payment_follow_ups: int}
     */
    public function dashboardInsights(User $user): array
    {
        $scores = CrmLeadScore::query()
            ->where('crm_lead_scores.company_id', $user->company_id)
            ->with(['lead.assignedUser', 'lead.status'])
            ->when($user->role?->value === 'sales', fn (Builder $query) => $query->whereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id)))
            ->latest('score');

        return [
            'hot_leads' => (clone $scores)->where('category', LeadScoreCategory::Hot)->limit(5)->get(),
            'at_risk_leads' => (clone $scores)->where('category', LeadScoreCategory::AtRisk)->limit(5)->get(),
            'hot_count' => (clone $scores)->where('category', LeadScoreCategory::Hot)->count(),
            'at_risk_count' => (clone $scores)->where('category', LeadScoreCategory::AtRisk)->count(),
            'follow_ups_today' => (clone $scores)->whereHas('lead', fn (Builder $lead) => $lead->whereDate('next_follow_up_at', today()))->count(),
            'overdue_follow_ups' => (clone $scores)->where('category', LeadScoreCategory::Hot)->whereHas('lead', fn (Builder $lead) => $lead->where('next_follow_up_at', '<', now()->startOfDay()))->count(),
            'partial_payment_follow_ups' => (clone $scores)->whereHas('lead.latestProforma', fn (Builder $proforma) => $proforma->where('status', ProformaStatus::PartiallyPaid))->count(),
        ];
    }

    private function calculate(CrmLead $lead): CrmLeadScoreResult
    {
        $weights = config('crm_ai.weights');
        $score = (int) ($weights['base'] ?? 20);
        $reasons = [];
        $risks = [];
        $opportunities = [];
        $stage = $this->stages->stageFor($lead);
        $quotation = $lead->latestQuotation;
        $proforma = $lead->latestProforma;
        $demo = $lead->latestDemoSchedule;
        $isOverdue = $lead->next_follow_up_at?->isBefore(now()->startOfDay()) && ! $stage->isTerminal();
        $isStale = $lead->latestActivity?->scheduled_at?->lt(now()->subDays((int) config('crm_ai.stale_activity_days', 3))) ?? true;
        $value = (float) ($proforma?->grand_total ?? $quotation?->grand_total ?? $lead->expected_value ?? 0);
        $isHighValue = $value >= (float) config('crm_ai.high_value_amount', 100000);

        if ($lead->phone) {
            $score += (int) ($weights['phone'] ?? 5);
            $reasons[] = 'Verified phone number is available.';
        }
        if ($lead->email) {
            $score += (int) ($weights['email'] ?? 5);
            $reasons[] = 'Email contact is available.';
        }
        if ($lead->business_name) {
            $score += (int) ($weights['company'] ?? 3);
        }
        if ($demo?->isActive()) {
            $score += (int) ($weights['demo_scheduled'] ?? 15);
            $reasons[] = 'A demo is scheduled.';
        }
        if ($demo?->completed_at) {
            $score += (int) ($weights['demo_completed'] ?? 20);
            $reasons[] = 'A demo has been completed.';
        }
        if ($quotation) {
            $score += (int) ($weights['quotation_created'] ?? 10);
            $reasons[] = 'A quotation has been prepared.';
        }
        if ($quotation?->status?->value === 'sent') {
            $score += (int) ($weights['quotation_sent'] ?? 10);
            $reasons[] = 'The quotation is with the prospect.';
        }
        if ($proforma?->status === ProformaStatus::Sent) {
            $score += (int) ($weights['proforma_sent'] ?? 20);
            $reasons[] = 'A proforma invoice has been sent.';
        }
        if ($proforma?->status === ProformaStatus::PartiallyPaid) {
            $score += (int) ($weights['partial_payment'] ?? 25);
            $reasons[] = 'A partial payment confirms purchase intent.';
        }
        if ($isHighValue) {
            $score += (int) ($weights['high_value'] ?? 10);
            $opportunities[] = 'High-value deal requires timely ownership.';
        }
        if ($lead->next_follow_up_at?->isToday()) {
            $score += (int) ($weights['follow_up_today'] ?? 5);
            $reasons[] = 'A follow-up is due today.';
        }
        if ($isOverdue) {
            $score -= (int) ($weights['overdue_follow_up'] ?? 10);
            $risks[] = 'The next follow-up is overdue.';
        }
        if ($isStale && ! $stage->isTerminal()) {
            $score -= (int) ($weights['stale_activity'] ?? 10);
            $risks[] = 'No recent sales activity is recorded.';
        }
        if ($stage->value === 'proposal' && $isStale) {
            $score -= (int) ($weights['stale_proposal'] ?? 8);
            $risks[] = 'The proposal needs a clear response or next step.';
        }

        $terminalCategory = match (true) {
            $stage->value === 'won' || $lead->converted_at !== null || $proforma?->status === ProformaStatus::Paid => LeadScoreCategory::Won,
            $stage->value === 'lost' || $lead->lost_at !== null => LeadScoreCategory::Lost,
            default => null,
        };
        $score = max(0, min(100, $score));
        $category = $terminalCategory ?? ($isOverdue || ($isStale && $stage->value === 'proposal') ? LeadScoreCategory::AtRisk : ($score >= (int) config('crm_ai.hot_threshold', 70) ? LeadScoreCategory::Hot : ($score >= (int) config('crm_ai.warm_threshold', 45) ? LeadScoreCategory::Warm : LeadScoreCategory::Cold)));
        $priority = $category === LeadScoreCategory::Lost ? LeadPriority::Low : ($isOverdue && $isHighValue ? LeadPriority::Urgent : ($category === LeadScoreCategory::Hot || $proforma?->status === ProformaStatus::PartiallyPaid ? LeadPriority::High : ($category === LeadScoreCategory::Warm || $isHighValue ? LeadPriority::Medium : LeadPriority::Low)));
        $confidence = collect([$lead->phone, $lead->email, $lead->business_name])->filter()->count();
        $nextBestAction = $this->nextBestAction($category, $proforma?->status, $quotation?->status?->value, $isOverdue, $lead->phone !== null, $lead->email !== null);

        return new CrmLeadScoreResult(
            score: $score,
            category: $category,
            confidence: $confidence >= 3 ? LeadScoreConfidence::High : ($confidence >= 2 ? LeadScoreConfidence::Medium : LeadScoreConfidence::Low),
            priority: $priority,
            nextBestAction: $nextBestAction,
            reasons: array_values(array_unique($reasons)),
            risks: array_values(array_unique($risks)),
            opportunities: array_values(array_unique($opportunities)),
            metadata: [
                'ruleset_version' => config('crm_ai.ruleset_version'),
                'pipeline_stage' => $stage->value,
                'deal_value' => $value,
                'currency' => $proforma?->currency ?? $quotation?->currency ?? $lead->currency ?? 'INR',
                'proforma_status' => $proforma?->status?->value,
                'is_overdue' => (bool) $isOverdue,
                'is_stale' => (bool) $isStale,
            ],
        );
    }

    private function nextBestAction(LeadScoreCategory $category, ?ProformaStatus $proformaStatus, ?string $quotationStatus, bool $isOverdue, bool $hasPhone, bool $hasEmail): string
    {
        if ($category === LeadScoreCategory::Won) return 'Thank the customer and hand over a clear next step.';
        if ($category === LeadScoreCategory::Lost) return 'Keep the outcome recorded; re-engage only with a relevant future reason.';
        if ($proformaStatus === ProformaStatus::PartiallyPaid) return 'Confirm the remaining payment date and resolve any final blocker.';
        if ($isOverdue) return $hasPhone ? 'Call today, then send a concise WhatsApp recap.' : 'Set a new owner and follow-up date today.';
        if ($quotationStatus === 'sent') return $hasEmail ? 'Ask for feedback on the quotation and offer a short decision call.' : 'Ask for a decision timeline and the next commercial step.';
        if ($category === LeadScoreCategory::Hot) return 'Book the next decision-making conversation while intent is high.';
        return 'Confirm the requirement and set a specific next follow-up.';
    }

    private function leadForAnalysis(CrmLead $lead): CrmLead
    {
        return CrmLead::query()->with(['status', 'latestDemoSchedule', 'latestQuotation', 'latestProforma', 'latestActivity', 'crmCustomer', 'leadScore'])->findOrFail($lead->id);
    }

    private function recordManualRefresh(CrmLead $lead, User $actor, CrmLeadScoreResult $result): void
    {
        CrmActivity::create([
            'company_id' => $lead->company_id,
            'crm_lead_id' => $lead->id,
            'assigned_user_id' => $lead->assigned_user_id,
            'created_by' => $actor->id,
            'type' => ActivityType::Note,
            'subject' => "AI lead score refreshed: {$result->score}/100 ({$result->category->label()})",
            'description' => 'Rule-based CRM score refreshed manually.',
            'scheduled_at' => now(),
            'completed_at' => now(),
            'priority' => $result->priority,
        ]);
        $this->auditLogger->record('crm.lead_score.refreshed', $lead, 'AI lead score refreshed manually', ['score' => $result->score, 'category' => $result->category->value, 'ruleset_version' => config('crm_ai.ruleset_version')]);
    }

    private function dispatchMeaningfulInsight(CrmLead $lead, ?CrmLeadScore $previous, CrmLeadScore $current, CrmLeadScoreResult $result, ?User $actor): void
    {
        $eventKey = match (true) {
            $result->category === LeadScoreCategory::Hot && $previous?->category !== LeadScoreCategory::Hot => 'crm.lead_score.hot',
            $result->category === LeadScoreCategory::AtRisk && (float) ($result->metadata['deal_value'] ?? 0) >= (float) config('crm_ai.high_value_amount') && $previous?->category !== LeadScoreCategory::AtRisk => 'crm.lead_score.at_risk',
            ($result->metadata['is_overdue'] ?? false) && $result->category === LeadScoreCategory::Hot && ! ($previous?->metadata['is_overdue'] ?? false) => 'crm.lead_followup_overdue',
            ($result->metadata['proforma_status'] ?? null) === ProformaStatus::PartiallyPaid->value && ($previous?->metadata['proforma_status'] ?? null) !== ProformaStatus::PartiallyPaid->value => 'crm.payment_followup_required',
            default => null,
        };

        if (! $eventKey) return;

        $this->domainEvents->dispatch(new LeadScoreInsight(
            key: $eventKey,
            companyId: $lead->company_id,
            actorId: $actor?->id,
            aggregateType: CrmLead::class,
            aggregateId: $lead->id,
            payload: [
                'lead_id' => $lead->id,
                'lead_title' => $lead->title,
                'business_name' => $lead->business_name,
                'assigned_user_id' => $lead->assigned_user_id,
                'score' => $result->score,
                'category' => $result->category->label(),
                'next_best_action' => $result->nextBestAction,
                'deal_value' => $result->metadata['deal_value'] ?? 0,
                'currency' => $result->metadata['currency'] ?? 'INR',
                'score_id' => $current->id,
            ],
        ));
    }
}
