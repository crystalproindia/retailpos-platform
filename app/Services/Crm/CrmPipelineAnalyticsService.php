<?php

namespace App\Services\Crm;

use App\Enums\Crm\PipelineStage;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStageHistory;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoiceReturn;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmActivity;
use App\Models\User;
use App\Repositories\Crm\LeadRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single read-side contract for CRM pipeline metrics. Values remain
 * deterministic: forecasts use stored opportunity values and probabilities.
 */
class CrmPipelineAnalyticsService
{
    public function __construct(private readonly LeadRepository $leads) {}

    /**
     * @param  array{branch_id?: int|string|null, assigned_user_id?: int|string|null, source_id?: int|string|null, from?: string|null, to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function summary(User $user, array $filters = []): array
    {
        // Funnel metrics are a lead-created-at cohort.  Transaction metrics are
        // deliberately calculated below from independently dated finance records.
        $leadQuery = $this->cohortLeads($user, $filters);
        $leads = (clone $leadQuery)->with(['status', 'stageHistory'])->get();
        $stageCounts = collect(PipelineStage::cases())->mapWithKeys(fn (PipelineStage $stage): array => [$stage->value => 0]);

        foreach ($leads as $lead) {
            $stage = $this->stageForLead($lead)->value;
            $stageCounts->put($stage, $stageCounts->get($stage) + 1);
        }

        $wins = $stageCounts[PipelineStage::Won->value];
        $lost = $stageCounts[PipelineStage::Lost->value];
        $closed = $wins + $lost;
        $open = $leads->filter(fn (CrmLead $lead): bool => ! $this->stageForLead($lead)->isTerminal());
        $leadIds = (clone $leadQuery)->select('crm_leads.id');

        $forecast = CrmOpportunity::query()
            ->where('company_id', $user->company_id)
            ->whereIn('lead_id', $leadIds)
            ->whereNotIn('stage', ['won', 'lost'])
            // A non-draft, non-void invoice is recognized commercial revenue,
            // not remaining pipeline forecast.
            ->whereDoesntHave('invoices', fn (Builder $query) => $query->whereNotIn('status', ['draft', 'cancelled', 'void']))
            ->selectRaw('COALESCE(SUM(expected_value * probability_percentage / 100), 0) as weighted_value')
            ->value('weighted_value');

        $lostReasons = CrmLeadStageHistory::query()
            ->where('company_id', $user->company_id)
            ->whereIn('lead_id', (clone $leadQuery)->select('crm_leads.id'))
            ->where('to_stage', PipelineStage::Lost->value)
            ->whereNotNull('lost_reason_snapshot')
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('occurred_at', '<=', $date))
            ->selectRaw('lost_reason_snapshot, COUNT(*) as total')
            ->groupBy('lost_reason_snapshot')
            ->orderByDesc('total')
            ->get()
            ->map(fn (CrmLeadStageHistory $history): array => [
                'reason' => $history->lost_reason_snapshot,
                'count' => (int) $history->getAttribute('total'),
            ])
            ->values();

        return [
            'total_leads' => $leads->count(),
            'open_leads' => $open->count(),
            'stage_counts' => $stageCounts,
            'won_count' => $wins,
            'lost_count' => $lost,
            'win_rate' => $closed === 0 ? null : round(($wins / $closed) * 100, 2),
            'average_stage_age_days' => $open->isEmpty() ? null : round($open->avg(function (CrmLead $lead): float {
                $enteredAt = $lead->stageHistory->first()?->occurred_at ?? $lead->created_at;

                return max(0, $enteredAt->diffInMinutes(now()) / 1440);
            }), 2),
            'forecast_weighted_value' => $this->normalizedAmount($forecast),
            'forecast_method' => 'Stored opportunity expected value multiplied by its configured stage probability. Won, lost, spam, and recognized invoice revenue are excluded.',
            'lost_reasons' => $lostReasons,
        ];
    }

    /**
     * @param  array{branch_id?: int|string|null, assigned_user_id?: int|string|null, source_id?: int|string|null, from?: string|null, to?: string|null}  $filters
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function stalledLeads(User $user, array $filters = []): \Illuminate\Support\Collection
    {
        $threshold = (int) config('crm_reports.stale_lead_days', 14);

        return $this->cohortLeads($user, $filters)
            ->with(['status', 'assignedUser', 'stageHistory', 'activities', 'quotations', 'proformas', 'invoices'])
            ->get()
            ->filter(fn (CrmLead $lead): bool => ! $this->stageForLead($lead)->isTerminal())
            ->map(function (CrmLead $lead) use ($threshold): ?array {
                $reopenedAt = $lead->stageHistory->firstWhere('event_type', 'reopened')?->occurred_at;
                $baseline = $reopenedAt ?? $lead->created_at;
                $lastActivity = $lead->activities->filter(fn ($activity) => $activity->scheduled_at?->gte($baseline))->max('scheduled_at') ?? $baseline;
                $oldQuotation = $lead->quotations->sortByDesc('created_at')->first();
                $quotationHasProgress = $oldQuotation && ($lead->proformas->contains(fn ($document) => $document->created_at->gte($oldQuotation->created_at))
                    || $lead->invoices->contains(fn ($document) => $document->created_at->gte($oldQuotation->created_at))
                    || $lead->activities->contains(fn ($activity) => $activity->scheduled_at?->gt($oldQuotation->created_at)));

                // Ordered reasons are the shared deterministic precedence contract.
                $reasons = [];
                if ($lead->next_follow_up_at?->isPast()) $reasons[] = 'Follow-up is overdue';
                if ($oldQuotation && $oldQuotation->created_at->lte(now()->subDays($threshold)) && ! $quotationHasProgress) $reasons[] = 'Quotation has no recorded progress';
                if ($lastActivity->lte(now()->subDays($threshold))) $reasons[] = "No meaningful CRM activity for {$threshold}+ days";

                return $reasons ? ['lead' => $lead, 'stage' => $this->stageForLead($lead), 'reason' => $reasons[0], 'matching_reasons' => $reasons, 'last_activity_at' => $lastActivity, 'stalled_days' => $lastActivity->diffInDays(now()), 'reopened_at' => $reopenedAt] : null;
            })
            ->filter()
            ->values();
    }

    /** @param array{branch_id?: int|string|null, assigned_user_id?: int|string|null, source_id?: int|string|null, from?: string|null, to?: string|null} $filters */
    public function forecast(User $user, array $filters = []): array
    {
        // Opportunity reporting is not a lead-created-at cohort: when a period is
        // selected, scheduled opportunities are matched on expected close in that
        // inclusive company-local date range. Unscheduled opportunities remain
        // separately visible rather than being silently treated as period work.
        // Actual Sales is a separate transaction-period measure.
        $leadIds = $this->scopedLeads($user, $filters)->select('crm_leads.id');
        $eligible = CrmOpportunity::query()->where('company_id', $user->company_id)
            ->whereIn('lead_id', $leadIds)
            ->whereNotIn('stage', ['won', 'lost'])
            ->whereDoesntHave('invoices', fn (Builder $query) => $query->whereNotIn('status', ['draft', 'cancelled', 'void']))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->where(fn (Builder $dates) => $dates->whereNull('expected_close_date')->orWhereDate('expected_close_date', '>=', $date)))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->where(fn (Builder $dates) => $dates->whereNull('expected_close_date')->orWhereDate('expected_close_date', '<=', $date)))
            ->with(['lead.status', 'assignedUser', 'lead.branch'])
            ->get();

        $rows = $eligible->map(function (CrmOpportunity $opportunity): array {
            $value = (float) ($opportunity->expected_value ?? 0);
            $probability = (int) ($opportunity->probability_percentage ?? 0);
            return ['opportunity' => $opportunity, 'stage' => $opportunity->stage, 'salesperson' => $opportunity->assignedUser?->name ?? 'Unassigned', 'outlet' => $opportunity->lead?->branch?->name ?? 'Legacy / unassigned', 'close_period' => $opportunity->expected_close_date?->format('Y-m') ?? 'Not scheduled', 'open_value' => $value, 'weighted_value' => round($value * $probability / 100, 2)];
        });

        $actualSales = $this->actualSales($user, $leadIds, $filters);
        $scheduled = $rows->where('close_period', '!=', 'Not scheduled');

        return [
            // Gross recognized invoices and finalized credit notes are independently
            // recognized in their own issue-date periods. This intentionally allows
            // negative actual sales in a return-only period.
            'gross_recognized_sales_value' => $actualSales['gross_recognized_sales_value'],
            'returns_credits_value' => $actualSales['returns_credits_value'],
            'actual_sales_value' => $actualSales['net_actual_sales_value'],
            'actual_sales_contract' => $this->actualSalesContract(),
            'open_pipeline_value' => $rows->sum('open_value'),
            'weighted_forecast' => $rows->sum('weighted_value'),
            // Expected Close Value is eligible open pipeline carrying a stored
            // expected-close date. Undated opportunities remain open/weighted.
            'expected_close_value' => $scheduled->sum('open_value'),
            'unscheduled_open_pipeline_value' => $rows->where('close_period', 'Not scheduled')->sum('open_value'),
            'rows' => $rows,
            'by_stage' => $this->forecastBreakdown($rows, 'stage'),
            'by_salesperson' => $this->forecastBreakdown($rows, 'salesperson'),
            'by_outlet' => $this->forecastBreakdown($rows, 'outlet'),
            'by_close_period' => $this->forecastBreakdown($scheduled, 'close_period'),
        ];
    }

    /**
     * Per-salesperson metrics. Every count is unique by lead or document ID.
     * Contacted/qualified are recorded stage-history arrivals; conversion is
     * unique won leads divided by eligible assigned leads. A sales cycle runs
     * from lead creation to the latest recorded Won transition (or won_at).
     * Actual sales uses recognized invoices, not document or opportunity totals.
     *
     * @param array{branch_id?: int|string|null, assigned_user_id?: int|string|null, source_id?: int|string|null, from?: string|null, to?: string|null} $filters
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function salespersonPerformance(User $user, array $filters = []): \Illuminate\Support\Collection
    {
        // Counts and conversion remain a lead-created-at cohort. Financial events
        // are attributed independently, by the authorized lead's current assignee:
        // invoices and credit notes have no salesperson field or immutable owner
        // snapshot in this domain, so historical attribution cannot be invented.
        $leads = $this->cohortLeads($user, $filters)
            ->with(['assignedUser', 'status', 'stageHistory', 'quotations', 'proformas', 'invoices'])
            ->get();
        $forecast = $this->forecast($user, $filters);
        $transactionLeads = $this->scopedLeads($user, $filters)->with('assignedUser')->get();
        $actualByLead = $this->actualSalesByLead($user, $this->scopedLeads($user, $filters)->select('crm_leads.id'), $filters);
        $transactionInvoiceCounts = $this->recognizedInvoices($user, $this->scopedLeads($user, $filters)->select('crm_leads.id'))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issue_date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issue_date', '<=', $date))
            ->get(['lead_id'])->countBy('lead_id');
        $stalledIds = $this->stalledLeads($user, $filters)->pluck('lead.id')->flip();
        $activities = CrmActivity::query()->where('company_id', $user->company_id)
            ->whereIn('crm_lead_id', $leads->pluck('id'))->get()->groupBy('crm_lead_id');

        $cohortByAssignee = $leads->groupBy(fn (CrmLead $lead) => $lead->assigned_user_id ?: 0);
        $transactionByAssignee = $transactionLeads->groupBy(fn (CrmLead $lead) => $lead->assigned_user_id ?: 0);

        return $cohortByAssignee->keys()->merge($transactionByAssignee->keys())->unique()->map(function ($assigneeId) use ($cohortByAssignee, $transactionByAssignee, $forecast, $stalledIds, $activities, $actualByLead, $transactionInvoiceCounts): array {
                $assignedLeads = $cohortByAssignee->get($assigneeId, collect());
                $transactionAssignedLeads = $transactionByAssignee->get($assigneeId, collect());
                $leadIds = $assignedLeads->pluck('id');
                $won = $assignedLeads->filter(fn (CrmLead $lead) => $this->stageForLead($lead) === PipelineStage::Won);
                $lost = $assignedLeads->filter(fn (CrmLead $lead) => $this->stageForLead($lead) === PipelineStage::Lost);
                $contacted = $assignedLeads->filter(fn (CrmLead $lead) => $lead->stageHistory->contains(fn (CrmLeadStageHistory $history) => $history->to_stage === PipelineStage::Contacted->value));
                $qualified = $assignedLeads->filter(fn (CrmLead $lead) => $lead->stageHistory->contains(fn (CrmLeadStageHistory $history) => $history->to_stage === 'qualified') || ($lead->status?->stage_type?->value ?? $lead->status?->stage_type) === 'qualified');
                $documents = fn (string $relation) => $assignedLeads->flatMap(fn (CrmLead $lead) => $lead->{$relation})->unique('id');
                $actual = $actualByLead->only($transactionAssignedLeads->pluck('id')->all())->reduce(fn (array $carry, array $amounts) => [
                    'gross' => $carry['gross'] + $amounts['gross'], 'credits' => $carry['credits'] + $amounts['credits'],
                ], ['gross' => 0, 'credits' => 0]);
                $leadActivities = $leadIds->flatMap(fn ($id) => $activities->get($id, collect()));
                $cycles = $won->map(function (CrmLead $lead): ?float {
                    $wonAt = $lead->stageHistory->first(fn (CrmLeadStageHistory $history) => $history->to_stage === PipelineStage::Won->value)?->occurred_at ?? $lead->won_at;
                    return $wonAt ? max(0, $lead->created_at->diffInMinutes($wonAt, false) / 1440) : null;
                })->filter(fn (?float $cycle): bool => $cycle !== null);
                $forecastRows = $forecast['rows']->filter(fn (array $row) => ($row['opportunity']->assigned_user_id ?: $row['opportunity']->lead?->assigned_user_id ?: 0) == $assigneeId);
                $person = ($assignedLeads->first() ?? $transactionAssignedLeads->first())?->assignedUser;

                return [
                    'salesperson_id' => (int) $assigneeId,
                    'salesperson' => $person?->name ?? 'Unassigned',
                    'leads_assigned' => $assignedLeads->count(),
                    'contacted' => $contacted->count(), 'qualified' => $qualified->count(),
                    'quotations' => $documents('quotations')->count(), 'proformas' => $documents('proformas')->count(),
                    'invoices' => $transactionInvoiceCounts->only($transactionAssignedLeads->pluck('id')->all())->sum(), 'won_leads' => $won->count(), 'lost_leads' => $lost->count(),
                    'conversion_rate' => $assignedLeads->isEmpty() ? null : round($won->count() / $assignedLeads->count() * 100, 2),
                    'gross_recognized_sales_value' => $this->minorToAmount($actual['gross']),
                    'returns_credits_value' => $this->minorToAmount($actual['credits']),
                    'actual_sales_value' => $this->minorToAmount($actual['gross'] - $actual['credits']),
                    'open_pipeline_value' => (float) $forecastRows->sum('open_value'), 'weighted_forecast' => (float) $forecastRows->sum('weighted_value'),
                    'average_sales_cycle_days' => $cycles->isEmpty() ? null : round($cycles->avg(), 2),
                    'follow_ups_completed' => $leadActivities->filter(fn (CrmActivity $activity) => $activity->completed_at !== null)->count(),
                    'follow_ups_overdue' => $leadActivities->filter(fn (CrmActivity $activity) => $activity->isOverdue())->count(),
                    'stalled_leads' => $leadIds->filter(fn (int $id) => $stalledIds->has($id))->count(),
                ];
            })->sortBy('salesperson')->values();
    }

    /** @param \Illuminate\Support\Collection<int, array<string, mixed>> $rows */
    private function forecastBreakdown(\Illuminate\Support\Collection $rows, string $key): \Illuminate\Support\Collection
    {
        return $rows->groupBy($key)->map(fn ($group) => [
            'open_value' => (float) $group->sum('open_value'),
            'weighted_value' => (float) $group->sum('weighted_value'),
        ]);
    }

    private function recognizedInvoices(User $user, Builder $leadIds): Builder
    {
        return CrmInvoice::query()->where('company_id', $user->company_id)
            ->whereIn('lead_id', $leadIds)->whereNotIn('status', ['draft', 'cancelled', 'void']);
    }

    /**
     * Returns gross, credits, and net transaction-period sales in integer minor
     * units. Invoice issue_date and credit-note issue_date are company-local dates;
     * filters are inclusive dates, not timestamps or lead creation dates.
     *
     * @return array{gross_recognized_sales_value:float,returns_credits_value:float,net_actual_sales_value:float}
     */
    private function actualSales(User $user, Builder $leadIds, array $filters): array
    {
        $byLead = $this->actualSalesByLead($user, $leadIds, $filters);
        $gross = $byLead->sum('gross');
        $credits = $byLead->sum('credits');

        return [
            'gross_recognized_sales_value' => $this->minorToAmount($gross),
            'returns_credits_value' => $this->minorToAmount($credits),
            'net_actual_sales_value' => $this->minorToAmount($gross - $credits),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array{gross:int,credits:int}> */
    private function actualSalesByLead(User $user, Builder $leadIds, array $filters): \Illuminate\Support\Collection
    {
        $invoiceScope = $this->recognizedInvoices($user, $leadIds);
        $invoices = (clone $invoiceScope)
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issue_date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issue_date', '<=', $date))
            ->get(['id', 'lead_id', 'grand_total']);
        $amounts = $invoices->groupBy('lead_id')->map(fn ($group) => [
            'gross' => $group->sum(fn (CrmInvoice $invoice) => $this->amountToMinor($invoice->grand_total)),
            'credits' => 0,
        ]);

        // Do not use invoices.credited_total here: it is a current lifetime balance.
        // Finalized credit-note rows are the authoritative dated return events.
        $returns = CrmInvoiceReturn::query()
            ->where('company_id', $user->company_id)
            ->where('status', CrmInvoiceReturn::STATUS_FINALIZED)
            ->whereHas('invoice', fn (Builder $query) => $query->whereIn('lead_id', (clone $leadIds))->whereNotIn('status', ['draft', 'cancelled', 'void']))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issue_date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issue_date', '<=', $date))
            ->with('invoice:id,lead_id')->get(['id', 'invoice_id', 'credit_total']);
        foreach ($returns as $return) {
            $leadId = (int) $return->invoice?->lead_id;
            if (! $leadId) continue;
            $current = $amounts->get($leadId, ['gross' => 0, 'credits' => 0]);
            $current['credits'] += $this->amountToMinor($return->credit_total);
            $amounts->put($leadId, $current);
        }

        return $amounts;
    }

    private function actualSalesContract(): string
    {
        return 'Actual Sales = gross recognized invoices issued in the selected inclusive company-local date range minus finalized credit notes issued in that range. Draft, cancelled, and void invoices are excluded. Returns are recognized in their own period, so a return-only period may be negative.';
    }

    private function amountToMinor(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function minorToAmount(int|float $amount): float
    {
        return round($amount / 100, 2);
    }

    /**
     * @param  array{branch_id?: int|string|null, assigned_user_id?: int|string|null, source_id?: int|string|null, from?: string|null, to?: string|null}  $filters
     * @return Builder<CrmLead>
     */
    private function scopedLeads(User $user, array $filters): Builder
    {
        return $this->leads->queryForUser($user)
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branchId) => $query->where('branch_id', (int) $branchId))
            ->when($filters['assigned_user_id'] ?? null, fn (Builder $query, int|string $assignee) => $query->where('assigned_user_id', (int) $assignee))
            ->when($filters['source_id'] ?? null, fn (Builder $query, int|string $source) => $query->where('source_id', (int) $source));
    }

    /** Lead-created-at cohort, used only for funnel/count metrics. */
    private function cohortLeads(User $user, array $filters): Builder
    {
        return $this->scopedLeads($user, $filters)
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function stageForLead(CrmLead $lead): PipelineStage
    {
        return match ($lead->status?->stage_type?->value ?? $lead->status?->stage_type) {
            'contacted', 'qualified', 'follow_up' => PipelineStage::Contacted,
            'demo_scheduled' => PipelineStage::DemoScheduled,
            'proposal' => PipelineStage::ProposalSent,
            'proforma_sent' => PipelineStage::ProformaSent,
            'partially_paid' => PipelineStage::PartiallyPaid,
            'won' => PipelineStage::Won,
            'lost', 'spam' => PipelineStage::Lost,
            default => PipelineStage::NewLead,
        };
    }

    private function normalizedAmount(mixed $amount): string
    {
        $amount = number_format(round((float) $amount, 2), 2, '.', '');

        return rtrim(rtrim($amount, '0'), '.') ?: '0';
    }
}
