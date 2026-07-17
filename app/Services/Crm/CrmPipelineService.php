<?php

namespace App\Services\Crm;

use App\Enums\Crm\PipelineStage;
use App\Enums\Crm\ProformaStatus;
use App\Models\Crm\CrmLead;
use App\Models\User;
use App\Repositories\Crm\LeadRepository;
use Illuminate\Database\Eloquent\Builder;

class CrmPipelineService
{
    public function __construct(
        private readonly LeadRepository $leadRepository,
        private readonly PipelineStageService $stageService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function forUser(User $user, array $filters = []): array
    {
        $statuses = $this->stageService->statusesForCompany($user->company_id);
        $leads = $this->filteredQuery($user, $filters)
            ->with(['latestQuotation', 'latestProforma', 'latestActivity', 'crmCustomer.activeOnboarding', 'leadScore'])
            ->latest('updated_at')
            ->get();

        $cards = $leads->map(fn (CrmLead $lead): array => $this->card($lead));
        if (filled($filters['stage'] ?? null)) {
            $cards = $cards->filter(fn (array $card): bool => $card['stage']->value === $filters['stage'])->values();
        }

        $columns = collect(PipelineStage::cases())->map(function (PipelineStage $stage) use ($cards, $statuses): array {
            $stageCards = $cards->filter(fn (array $card): bool => $card['stage'] === $stage)->values();

            return [
                'stage' => $stage,
                'status' => $statuses->get($stage->value),
                'cards' => $stageCards,
                'count' => $stageCards->count(),
                'value' => $stageCards->sum(fn (array $card): float => (float) $card['value']),
            ];
        });

        $openCards = $cards->filter(fn (array $card): bool => ! $card['stage']->isTerminal());

        return [
            'columns' => $columns,
            'cards' => $cards,
            'metrics' => [
                'active_deals' => $openCards->count(),
                'pipeline_value' => $openCards->sum(fn (array $card): float => (float) $card['value']),
                'won_value' => $cards->where('stage', PipelineStage::Won)->sum(fn (array $card): float => (float) $card['value']),
                'overdue_follow_ups' => $openCards->where('is_overdue', true)->count(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CrmLead>
     */
    private function filteredQuery(User $user, array $filters): Builder
    {
        return $this->leadRepository->queryForUser($user)
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['assigned_user_id'] ?? null, fn (Builder $query, int|string $userId) => $query->where('assigned_user_id', $userId))
            ->when($filters['source_id'] ?? null, fn (Builder $query, int|string $sourceId) => $query->where('source_id', $sourceId))
            ->when($filters['created_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['activity_from'] ?? null, fn (Builder $query, string $date) => $query->whereHas('activities', fn (Builder $activities) => $activities->whereDate('scheduled_at', '>=', $date)))
            ->when($filters['activity_to'] ?? null, fn (Builder $query, string $date) => $query->whereHas('activities', fn (Builder $activities) => $activities->whereDate('scheduled_at', '<=', $date)))
            ->when($filters['min_value'] ?? null, fn (Builder $query, string $value) => $query->where('expected_value', '>=', $value))
            ->when($filters['max_value'] ?? null, fn (Builder $query, string $value) => $query->where('expected_value', '<=', $value))
            ->when($filters['follow_up'] ?? null, function (Builder $query, string $followUp): void {
                match ($followUp) {
                    'overdue' => $query->where('next_follow_up_at', '<', now()->startOfDay()),
                    'today' => $query->whereDate('next_follow_up_at', today()),
                    'upcoming' => $query->where('next_follow_up_at', '>', now()->endOfDay()),
                    'none' => $query->whereNull('next_follow_up_at'),
                    default => null,
                };
            })
            ->when($filters['payment_status'] ?? null, function (Builder $query, string $paymentStatus): void {
                $statuses = match ($paymentStatus) {
                    'partial' => [ProformaStatus::PartiallyPaid->value],
                    'paid' => [ProformaStatus::Paid->value],
                    'pending' => [ProformaStatus::Draft->value, ProformaStatus::Sent->value, ProformaStatus::Overdue->value],
                    default => [],
                };

                if ($statuses !== []) {
                    $query->whereHas('proformas', fn (Builder $proformas) => $proformas->whereIn('status', $statuses));
                }
            })
            ->when($filters['ai_category'] ?? null, fn (Builder $query, string $category) => $query->whereHas('leadScore', fn (Builder $score) => $score->where('category', $category)))
            ->when($filters['ai_priority'] ?? null, fn (Builder $query, string $priority) => $query->whereHas('leadScore', fn (Builder $score) => $score->where('priority', $priority)));
    }

    /**
     * @return array<string, mixed>
     */
    private function card(CrmLead $lead): array
    {
        $stage = $this->stageService->stageFor($lead);
        $quotation = $lead->latestQuotation;
        $proforma = $lead->latestProforma;
        $value = $proforma?->grand_total ?? $quotation?->grand_total ?? $lead->expected_value ?? 0;
        $currency = $proforma?->currency ?? $quotation?->currency ?? $lead->currency ?? 'INR';
        $followUp = $lead->next_follow_up_at;

        return [
            'lead' => $lead,
            'stage' => $stage,
            'value' => $value,
            'currency' => $currency,
            'latest_activity' => $lead->latestActivity,
            'latest_demo' => $lead->latestDemoSchedule,
            'latest_quotation' => $quotation,
            'latest_proforma' => $proforma,
            'is_overdue' => $followUp?->isPast() && ! $followUp->isToday() && ! $stage->isTerminal(),
            'is_due_today' => $followUp?->isToday() && ! $stage->isTerminal(),
            'payment_label' => $proforma?->status === ProformaStatus::PartiallyPaid
                ? 'Partial payment'
                : ($proforma?->status === ProformaStatus::Paid ? 'Paid' : null),
            'ai_score' => $lead->leadScore,
            'active_onboarding' => $lead->crmCustomer?->activeOnboarding,
        ];
    }
}
