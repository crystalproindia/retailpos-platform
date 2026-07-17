<?php

namespace App\Services\Crm;

use App\Enums\Crm\LeadScoreCategory;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\OnboardingStatus;
use App\Enums\Crm\SupportTicketStatus;
use App\Enums\UserRole;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmCustomerPortalUser;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadScore;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmProformaPayment;
use App\Models\Crm\CrmQuotation;
use App\Models\Crm\CrmSupportTicket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmExecutiveReportService
{
    /** @param array<string, mixed> $filters */
    public function dashboard(User $user, array $filters = []): array
    {
        $range = $this->dateRange($filters);
        $sales = $this->salesHealth($user, $filters, $range);
        $money = $this->moneyHealth($user, $filters, $range);
        $onboarding = $this->onboardingHealth($user, $filters, $range);
        $support = $this->supportHealth($user, $filters, $range);
        $customers = $this->customerGrowthHealth($user, $filters, $range);
        $areas = compact('sales', 'money', 'onboarding', 'support', 'customers');
        $weights = config('crm_reports.health_weights');
        $score = (int) round(collect($areas)->sum(fn (array $area, string $key): float => $area['score'] * ((float) ($weights[$key === 'customers' ? 'customer_growth' : $key] ?? 0) / 100)));

        return [
            'range' => $range,
            'filters' => $filters,
            'overall_score' => $score,
            'overall' => $this->mood($score),
            'summary_message' => $this->summary($score, $areas),
            'areas' => $areas,
            'risks' => $this->risks($areas),
            'positives' => $this->positives($areas),
            'actions' => $this->actions($areas),
            'charts' => [
                // Historical snapshots can replace this current baseline without changing the view contract.
                'health_trend' => collect([['label' => 'Current', 'value' => $score]]),
                'collections' => $this->collectionTrend($user, $filters, $range),
                'pipeline' => $this->pipelineStages($user, $filters),
                'sources' => $this->leadSources($user, $filters, $range),
                'support_priorities' => $this->supportPriorities($user, $filters, $range),
                'onboarding_statuses' => $this->onboardingStatuses($user, $filters, $range),
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function report(User $user, string $report, array $filters = []): array
    {
        $dashboard = $this->dashboard($user, $filters);
        $range = $dashboard['range'];

        return match ($report) {
            'sales' => $dashboard + ['detail' => ['sources' => $dashboard['charts']['sources'], 'pipeline' => $dashboard['charts']['pipeline'], 'team' => $this->salesTeam($user, $filters, $range), 'quotations' => $this->quotationStatuses($user, $filters, $range), 'hot_leads' => $this->hotLeads($user, $filters), 'overdue_follow_ups' => $this->overdueFollowUps($user, $filters)]],
            'payments' => $dashboard + ['detail' => ['pending_customers' => $this->pendingCustomers($user, $filters, $range), 'payment_modes' => $this->paymentModes($user, $filters, $range)]],
            'onboarding' => $dashboard + ['detail' => ['statuses' => $dashboard['charts']['onboarding_statuses'], 'owners' => $this->onboardingOwners($user, $filters, $range)]],
            'support' => $dashboard + ['detail' => ['priorities' => $dashboard['charts']['support_priorities'], 'categories' => $this->supportCategories($user, $filters, $range), 'owners' => $this->supportOwners($user, $filters, $range)]],
            'customers' => $dashboard + ['detail' => ['customer_statuses' => $this->customerStatuses($user, $filters, $range), 'customer_support' => $this->customerSupportLoad($user, $filters, $range), 'onboarding_statuses' => $dashboard['charts']['onboarding_statuses']]],
            default => $dashboard,
        };
    }

    /** @param array<string, mixed> $filters */
    private function salesHealth(User $user, array $filters, array $range): array
    {
        $all = $this->leads($user, $filters);
        $period = $this->inRange(clone $all, 'created_at', $range);
        $open = (clone $all)->whereHas('status', fn (Builder $query) => $query->where('is_won', false)->where('is_lost', false));
        $hot = (clone $all)->whereHas('leadScore', fn (Builder $query) => $query->where('category', LeadScoreCategory::Hot->value))->count();
        $atRisk = (clone $all)->whereHas('leadScore', fn (Builder $query) => $query->where('category', LeadScoreCategory::AtRisk->value))->count();
        $stale = (clone $open)->where('updated_at', '<', now()->subDays((int) config('crm_reports.stale_lead_days')))->count();
        $overdue = (clone $open)->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', now())->count();
        $won = (clone $all)->whereHas('status', fn (Builder $query) => $query->where('is_won', true));
        $lost = (clone $all)->whereHas('status', fn (Builder $query) => $query->where('is_lost', true));
        $wonCount = (clone $won)->count();
        $lostCount = (clone $lost)->count();
        $score = $this->clamp(62 + min(18, $hot * 4) - min(22, $stale * 5) - min(18, $overdue * 4) - (($wonCount + $lostCount) ? round($lostCount / ($wonCount + $lostCount) * 18) : 0));

        return $this->area($score, 'Sales Health', [
            'total_leads' => (clone $period)->count(), 'new_leads' => (clone $period)->whereHas('status', fn (Builder $query) => $query->where('stage_type', LeadStageType::New->value))->count(),
            'hot_leads' => $hot, 'at_risk_leads' => $atRisk, 'pipeline_value' => (float) (clone $open)->sum('expected_value'), 'won_value' => (float) (clone $won)->sum('expected_value'), 'lost_value' => (float) (clone $lost)->sum('expected_value'), 'won_lost_ratio' => $lostCount ? round($wonCount / $lostCount, 1) : $wonCount, 'stale_leads' => $stale, 'follow_ups_overdue' => $overdue,
        ], $stale || $overdue ? 'Re-engage stale leads and clear overdue follow-ups.' : 'Keep momentum with qualified leads and active demos.');
    }

    /** @param array<string, mixed> $filters */
    private function moneyHealth(User $user, array $filters, array $range): array
    {
        $proformas = $this->inRange($this->proformas($user, $filters), 'invoice_date', $range);
        $total = (float) (clone $proformas)->sum('grand_total');
        $paid = (float) (clone $proformas)->sum('paid_amount');
        $pending = (float) (clone $proformas)->sum('balance_amount');
        $overdue = (clone $proformas)->where('due_date', '<', today())->whereNotIn('status', ['paid', 'cancelled']);
        $overdueAmount = (float) (clone $overdue)->sum('balance_amount');
        $collectionRate = $total > 0 ? round($paid / $total * 100, 1) : 65;
        $score = $this->clamp(38 + ($collectionRate * 0.6) - ($total > 0 ? ($overdueAmount / $total * 35) : 0) - min(15, (clone $overdue)->count() * 3));

        return $this->area($score, 'Money Health', [
            'total_proforma_value' => $total, 'paid_amount' => $paid, 'pending_amount' => $pending, 'overdue_amount' => $overdueAmount, 'partially_paid' => (clone $proformas)->where('status', 'partially_paid')->count(), 'fully_paid' => (clone $proformas)->where('status', 'paid')->count(), 'collection_rate' => $collectionRate,
        ], $overdueAmount > 0 ? 'Prioritize overdue balances before new payment commitments.' : 'Keep collection momentum and monitor pending proformas.');
    }

    /** @param array<string, mixed> $filters */
    private function onboardingHealth(User $user, array $filters, array $range): array
    {
        $all = $this->onboardings($user, $filters);
        $period = $this->inRange(clone $all, 'created_at', $range);
        $active = (clone $all)->whereNotIn('status', [OnboardingStatus::Live->value, OnboardingStatus::Cancelled->value]);
        $delayed = (clone $active)->whereNotNull('target_go_live_date')->whereDate('target_go_live_date', '<', today())->count();
        $blocked = $this->blockedTasks($user, $filters);
        $waitingCustomer = (clone $active)->where('status', OnboardingStatus::WaitingForCustomer->value)->count();
        $waitingTeam = (clone $active)->where('status', OnboardingStatus::WaitingForTeam->value)->count();
        $score = $this->clamp(78 - min(30, $delayed * 10) - min(20, $blocked * 6) - min(12, ($waitingCustomer + $waitingTeam) * 2));

        return $this->area($score, 'Onboarding Health', [
            'active_onboardings' => (clone $active)->count(), 'waiting_for_customer' => $waitingCustomer, 'waiting_for_team' => $waitingTeam, 'go_live_ready' => (clone $all)->where('status', OnboardingStatus::GoLiveReady->value)->count(), 'live_this_month' => (clone $all)->where('status', OnboardingStatus::Live->value)->whereBetween('actual_go_live_date', [now()->startOfMonth(), now()->endOfMonth()])->count(), 'delayed_onboardings' => $delayed, 'blocked_tasks' => $blocked, 'started_in_range' => (clone $period)->count(),
        ], $delayed || $blocked ? 'Clear blocked tasks and protect delayed go-live dates.' : 'Keep implementation owners aligned on the next customer action.');
    }

    /** @param array<string, mixed> $filters */
    private function supportHealth(User $user, array $filters, array $range): array
    {
        $all = $this->tickets($user, $filters);
        $period = $this->inRange(clone $all, 'created_at', $range);
        $open = (clone $all)->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value]);
        $urgent = (clone $open)->where('priority', 'urgent')->count();
        $overdue = (clone $open)->where('due_at', '<', now())->count();
        $score = $this->clamp(82 - min(35, $overdue * 12) - min(24, $urgent * 7) - min(12, (clone $open)->where('status', SupportTicketStatus::WaitingForInternalTeam->value)->count() * 2));

        return $this->area($score, 'Support Health', [
            'open_tickets' => (clone $open)->count(), 'urgent_tickets' => $urgent, 'overdue_tickets' => $overdue, 'waiting_for_customer' => (clone $open)->where('status', SupportTicketStatus::WaitingForCustomer->value)->count(), 'waiting_for_internal_team' => (clone $open)->where('status', SupportTicketStatus::WaitingForInternalTeam->value)->count(), 'resolved_this_month' => (clone $all)->whereBetween('resolved_at', [now()->startOfMonth(), now()->endOfMonth()])->count(), 'reopened_tickets' => (clone $period)->whereNotNull('reopened_at')->count(),
        ], $urgent || $overdue ? 'Assign urgent tickets and clear SLA-overdue work first.' : 'Support workload is controlled. Keep response quality consistent.');
    }

    /** @param array<string, mixed> $filters */
    private function customerGrowthHealth(User $user, array $filters, array $range): array
    {
        $requests = $this->inRange($this->leads($user, $filters)->whereHas('source', fn (Builder $query) => $query->where('slug', 'customer-portal')), 'created_at', $range);
        $customers = $this->customers($user, $filters);
        $newCustomers = $this->inRange(clone $customers, 'created_at', $range)->count();
        $activeCustomers = (clone $customers)->where('status', 'active')->count();
        $uncontacted = (clone $requests)->whereNull('last_contacted_at')->count();
        $highUrgency = (clone $requests)->where('priority', 'high')->count();
        $count = (clone $requests)->count();
        $score = $this->clamp(58 + min(26, $count * 7) - min(24, $uncontacted * 8) - min(12, $highUrgency * 3));

        return $this->area($score, 'Customer Growth Health', [
            'new_customers' => $newCustomers, 'active_customers' => $activeCustomers, 'portal_service_requests' => $count, 'repeat_business_leads' => (clone $requests)->whereNotNull('customer_id')->count(), 'existing_customer_upsell_requests' => (clone $requests)->whereNotNull('customer_id')->count(), 'high_urgency_requests' => $highUrgency, 'uncontacted_requests' => $uncontacted, 'converted_requests' => (clone $requests)->whereNotNull('won_at')->count(), 'portal_users' => $this->portalUsers($user, $filters),
        ], $uncontacted ? 'Contact customer service requests before they become cold.' : ($count ? 'Keep responding to existing-customer growth opportunities.' : 'Invite eligible customers to use the service request portal.'));
    }

    /** @param array<string, mixed> $filters */
    private function collectionTrend(User $user, array $filters, array $range): Collection
    {
        $start = $range['from']->startOfMonth(); $end = $range['to']->endOfMonth();
        $months = max(0, (int) $start->diffInMonths($end, false));

        return collect(range(0, $months))->map(function (int $offset) use ($user, $filters, $start): array {
            $month = $start->addMonths($offset);
            $payments = CrmProformaPayment::query()->whereBetween('payment_date', [$month->startOfMonth(), $month->endOfMonth()])->whereHas('proformaInvoice', fn (Builder $query) => $this->scopeProformas($query, $user, $filters));
            return ['label' => $month->format('M'), 'paid' => (float) $payments->sum('amount'), 'pending' => (float) $this->proformas($user, $filters)->whereBetween('invoice_date', [$month->startOfMonth(), $month->endOfMonth()])->sum('balance_amount')];
        });
    }

    /** @param array<string, mixed> $filters */
    private function pipelineStages(User $user, array $filters): Collection { return $this->leads($user, $filters)->join('crm_lead_statuses', 'crm_leads.status_id', '=', 'crm_lead_statuses.id')->selectRaw('crm_lead_statuses.name as label, COALESCE(SUM(crm_leads.expected_value), 0) as value')->groupBy('crm_lead_statuses.id', 'crm_lead_statuses.name')->orderBy('crm_lead_statuses.sort_order')->get(); }
    /** @param array<string, mixed> $filters */
    private function leadSources(User $user, array $filters, array $range): Collection { return $this->inRange($this->leads($user, $filters), 'created_at', $range)->join('crm_lead_sources', 'crm_leads.source_id', '=', 'crm_lead_sources.id')->selectRaw('crm_lead_sources.name as label, COUNT(*) as value')->groupBy('crm_lead_sources.id', 'crm_lead_sources.name')->orderByDesc('value')->get(); }
    /** @param array<string, mixed> $filters */
    private function supportPriorities(User $user, array $filters, array $range): Collection { return $this->inRange($this->tickets($user, $filters), 'created_at', $range)->selectRaw('priority as label, COUNT(*) as value')->groupBy('priority')->get(); }
    /** @param array<string, mixed> $filters */
    private function onboardingStatuses(User $user, array $filters, array $range): Collection { return $this->inRange($this->onboardings($user, $filters), 'created_at', $range)->selectRaw('status as label, COUNT(*) as value')->groupBy('status')->get(); }
    /** @param array<string, mixed> $filters */
    private function salesTeam(User $user, array $filters, array $range): Collection { return $this->inRange($this->leads($user, $filters), 'created_at', $range)->leftJoin('users', 'crm_leads.assigned_user_id', '=', 'users.id')->selectRaw("COALESCE(users.name, 'Unassigned') as label, COUNT(*) as leads, COALESCE(SUM(crm_leads.expected_value), 0) as value")->groupBy('users.id', 'users.name')->orderByDesc('value')->get(); }
    /** @param array<string, mixed> $filters */
    private function hotLeads(User $user, array $filters): Collection { return $this->leads($user, $filters)->with(['assignedUser', 'leadScore'])->whereHas('leadScore', fn (Builder $query) => $query->whereIn('category', [LeadScoreCategory::Hot->value, LeadScoreCategory::AtRisk->value]))->latest('updated_at')->limit(10)->get(); }
    /** @param array<string, mixed> $filters */
    private function overdueFollowUps(User $user, array $filters): Collection { return $this->leads($user, $filters)->with('assignedUser')->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', now())->orderBy('next_follow_up_at')->limit(10)->get(); }
    /** @param array<string, mixed> $filters */
    private function quotationStatuses(User $user, array $filters, array $range): Collection { return $this->inRange($this->quotations($user, $filters), 'created_at', $range)->selectRaw('status as label, COUNT(*) as value')->groupBy('status')->orderByDesc('value')->get(); }
    /** @param array<string, mixed> $filters */
    private function pendingCustomers(User $user, array $filters, array $range): Collection { return $this->inRange($this->proformas($user, $filters), 'invoice_date', $range)->with('customer')->where('balance_amount', '>', 0)->orderByDesc('balance_amount')->limit(10)->get(); }
    /** @param array<string, mixed> $filters */
    private function paymentModes(User $user, array $filters, array $range): Collection { return CrmProformaPayment::query()->whereBetween('payment_date', [$range['from'], $range['to']])->whereHas('proformaInvoice', fn (Builder $query) => $this->scopeProformas($query, $user, $filters))->selectRaw('payment_mode as label, SUM(amount) as value')->groupBy('payment_mode')->orderByDesc('value')->get(); }
    /** @param array<string, mixed> $filters */
    private function onboardingOwners(User $user, array $filters, array $range): Collection { return $this->inRange($this->onboardings($user, $filters), 'created_at', $range)->leftJoin('users', 'crm_customer_onboardings.implementation_owner_id', '=', 'users.id')->selectRaw("COALESCE(users.name, 'Unassigned') as label, COUNT(*) as value")->groupBy('users.id', 'users.name')->orderByDesc('value')->get(); }
    /** @param array<string, mixed> $filters */
    private function supportCategories(User $user, array $filters, array $range): Collection { return $this->inRange($this->tickets($user, $filters), 'created_at', $range)->selectRaw('category as label, COUNT(*) as value')->groupBy('category')->orderByDesc('value')->get(); }
    /** @param array<string, mixed> $filters */
    private function supportOwners(User $user, array $filters, array $range): Collection { return $this->inRange($this->tickets($user, $filters), 'created_at', $range)->leftJoin('users', 'crm_support_tickets.assigned_to', '=', 'users.id')->selectRaw("COALESCE(users.name, 'Unassigned') as label, COUNT(*) as value")->groupBy('users.id', 'users.name')->orderByDesc('value')->get(); }
    /** @param array<string, mixed> $filters */
    private function customerStatuses(User $user, array $filters, array $range): Collection { return $this->inRange($this->customers($user, $filters), 'created_at', $range)->selectRaw('status as label, COUNT(*) as value')->groupBy('status')->get(); }
    /** @param array<string, mixed> $filters */
    private function customerSupportLoad(User $user, array $filters, array $range): Collection { return $this->inRange($this->tickets($user, $filters), 'created_at', $range)->with('customer')->whereNotNull('customer_id')->selectRaw('customer_id, COUNT(*) as value')->groupBy('customer_id')->orderByDesc('value')->limit(10)->get(); }

    /** @param array<string, mixed> $filters */
    private function leads(User $user, array $filters): Builder { $query = CrmLead::query()->where('crm_leads.company_id', $user->company_id); $this->scopeLeads($query, $user, $filters); return $query; }
    /** @param array<string, mixed> $filters */
    private function proformas(User $user, array $filters): Builder { $query = CrmProformaInvoice::query(); $this->scopeProformas($query, $user, $filters); return $query; }
    /** @param array<string, mixed> $filters */
    private function onboardings(User $user, array $filters): Builder { $query = CrmCustomerOnboarding::query()->where('crm_customer_onboardings.company_id', $user->company_id); $this->scopeOnboardings($query, $user, $filters); return $query; }
    /** @param array<string, mixed> $filters */
    private function tickets(User $user, array $filters): Builder { $query = CrmSupportTicket::query()->where('crm_support_tickets.company_id', $user->company_id); $this->scopeTickets($query, $user, $filters); return $query; }
    /** @param array<string, mixed> $filters */
    private function customers(User $user, array $filters): Builder { $query = CrmCustomer::query()->where('crm_customers.company_id', $user->company_id); if ($this->isSales($user)) $query->whereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id)); if ($filters['customer_id'] ?? null) $query->whereKey($filters['customer_id']); return $query; }
    /** @param array<string, mixed> $filters */
    private function quotations(User $user, array $filters): Builder { $query = CrmQuotation::query()->where('crm_quotations.company_id', $user->company_id); if ($this->isSales($user)) $query->whereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id)); if ($filters['customer_id'] ?? null) $query->whereHas('lead', fn (Builder $lead) => $lead->where('customer_id', $filters['customer_id'])); return $query; }
    /** @param array<string, mixed> $filters */
    private function scopeLeads(Builder $query, User $user, array $filters): void { if ($this->isSales($user)) $query->where(fn (Builder $q) => $q->where('assigned_user_id', $user->id)->orWhere('created_by', $user->id)); if ($filters['assigned_user_id'] ?? null) $query->where('assigned_user_id', $filters['assigned_user_id']); if ($filters['source_id'] ?? null) $query->where('source_id', $filters['source_id']); if ($filters['status_id'] ?? null) $query->where('status_id', $filters['status_id']); if ($filters['customer_id'] ?? null) $query->where('customer_id', $filters['customer_id']); }
    /** @param array<string, mixed> $filters */
    private function scopeProformas(Builder $query, User $user, array $filters): void { $query->where('crm_proforma_invoices.company_id', $user->company_id); if ($this->isSales($user)) $query->whereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id)); if ($filters['customer_id'] ?? null) $query->where('customer_id', $filters['customer_id']); }
    /** @param array<string, mixed> $filters */
    private function scopeOnboardings(Builder $query, User $user, array $filters): void { if ($this->isSales($user)) $query->where(fn (Builder $q) => $q->where('assigned_to', $user->id)->orWhere('implementation_owner_id', $user->id)); if ($filters['assigned_user_id'] ?? null) $query->where(fn (Builder $q) => $q->where('assigned_to', $filters['assigned_user_id'])->orWhere('implementation_owner_id', $filters['assigned_user_id'])); if ($filters['customer_id'] ?? null) $query->where('customer_id', $filters['customer_id']); }
    /** @param array<string, mixed> $filters */
    private function scopeTickets(Builder $query, User $user, array $filters): void { if ($this->isSales($user)) $query->where(fn (Builder $q) => $q->where('created_by', $user->id)->orWhere('assigned_to', $user->id)->orWhereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id))); if ($filters['assigned_user_id'] ?? null) $query->where('assigned_to', $filters['assigned_user_id']); if ($filters['customer_id'] ?? null) $query->where('customer_id', $filters['customer_id']); }
    /** @param array<string, mixed> $filters */
    private function blockedTasks(User $user, array $filters): int { return \App\Models\Crm\CrmOnboardingTask::query()->where('status', 'blocked')->whereHas('onboarding', function (Builder $query) use ($user, $filters): void { $query->where('company_id', $user->company_id); $this->scopeOnboardings($query, $user, $filters); })->count(); }
    /** @param array<string, mixed> $filters */
    private function portalUsers(User $user, array $filters): int { return CrmCustomerPortalUser::query()->whereHas('customer', function (Builder $query) use ($user, $filters): void { $query->where('company_id', $user->company_id); if ($this->isSales($user)) $query->whereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id)); if ($filters['customer_id'] ?? null) $query->whereKey($filters['customer_id']); })->count(); }
    private function inRange(Builder $query, string $column, array $range): Builder { return $query->whereBetween($query->getModel()->qualifyColumn($column), [$range['from']->startOfDay(), $range['to']->endOfDay()]); }
    /** @param array<string, mixed> $filters */
    private function dateRange(array $filters): array { $preset = $filters['range'] ?? 'this_month'; $now = CarbonImmutable::now(); [$from, $to] = match ($preset) { 'last_month' => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth()], 'last_3_months' => [$now->subMonths(2)->startOfMonth(), $now->endOfMonth()], 'last_6_months' => [$now->subMonths(5)->startOfMonth(), $now->endOfMonth()], 'this_year' => [$now->startOfYear(), $now->endOfYear()], 'custom' => [filled($filters['date_from'] ?? null) ? CarbonImmutable::parse($filters['date_from'])->startOfDay() : $now->startOfMonth(), filled($filters['date_to'] ?? null) ? CarbonImmutable::parse($filters['date_to'])->endOfDay() : $now->endOfDay()], default => [$now->startOfMonth(), $now->endOfMonth()] }; return compact('preset', 'from', 'to'); }
    private function area(int $score, string $label, array $metrics, string $action): array { return ['label' => $label, 'score' => $score, 'mood' => $this->mood($score), 'metrics' => $metrics, 'action' => $action]; }
    private function mood(int $score): array { $thresholds = config('crm_reports.health_thresholds'); return match (true) { $score >= $thresholds['healthy'] => ['label' => 'Healthy', 'icon' => '😊', 'tone' => 'success'], $score >= $thresholds['attention'] => ['label' => 'Needs Attention', 'icon' => '😐', 'tone' => 'warning'], $score >= $thresholds['risk'] => ['label' => 'Risk', 'icon' => '😟', 'tone' => 'danger'], default => ['label' => 'Critical', 'icon' => '😡', 'tone' => 'critical'] }; }
    private function summary(int $score, array $areas): string { $weakest = collect($areas)->sortBy('score')->first(); return $score >= 80 ? 'Business operations are healthy. Keep the current follow-up and delivery cadence.' : "Business needs attention in {$weakest['label']}. {$weakest['action']}"; }
    private function risks(array $areas): Collection { return collect($areas)->map(function (array $area, string $key): array { $metrics = $area['metrics']; $count = match ($key) { 'sales' => $metrics['follow_ups_overdue'] + $metrics['stale_leads'], 'money' => $metrics['overdue_amount'], 'onboarding' => $metrics['delayed_onboardings'] + $metrics['blocked_tasks'], 'support' => $metrics['urgent_tickets'] + $metrics['overdue_tickets'], default => $metrics['uncontacted_requests'] + $metrics['high_urgency_requests'], }; return ['area' => $area['label'], 'score' => $area['score'], 'count' => $count, 'action' => $area['action']]; })->filter(fn (array $risk) => $risk['count'] > 0)->sortBy('score')->take(5)->values(); }
    private function actions(array $areas): Collection { return collect($areas)->sortBy('score')->take(5)->map(fn (array $area) => ['area' => $area['label'], 'action' => $area['action'], 'score' => $area['score']])->values(); }
    private function positives(array $areas): Collection { return collect($areas)->filter(fn (array $area) => $area['score'] >= 80)->sortByDesc('score')->take(3)->map(fn (array $area) => ['area' => $area['label'], 'score' => $area['score'], 'message' => "{$area['label']} is currently performing well."])->values(); }
    private function clamp(float $score): int { return (int) max(0, min(100, round($score))); }
    private function isSales(User $user): bool { return ($user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role)) === UserRole::Sales; }
}
