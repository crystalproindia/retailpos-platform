<?php

namespace App\Repositories\Crm;

use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmTag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeadRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->queryForUser($user, ($filters['trashed'] ?? null) === 'with')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['status_id'] ?? null, fn (Builder $query, int|string $statusId) => $query->where('status_id', $statusId))
            ->when($filters['source_id'] ?? null, fn (Builder $query, int|string $sourceId) => $query->where('source_id', $sourceId))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['assigned_user_id'] ?? null, fn (Builder $query, int|string $userId) => $query->where('assigned_user_id', $userId))
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function findForUser(User $user, int $leadId, bool $withTrashed = false): CrmLead
    {
        return $this->queryForUser($user, $withTrashed)
            ->with(['activities.assignedUser', 'notes.user', 'tags', 'crmCompany', 'contact'])
            ->findOrFail($leadId);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardMetrics(User $user): array
    {
        $base = $this->baseQueryForUser($user);

        $stageCount = fn (LeadStageType $stage): int => (clone $base)
            ->whereHas('status', fn (Builder $query) => $query->where('stage_type', $stage->value))
            ->count();

        $statusBreakdown = $this->statusesForCompany($user->company_id)
            ->map(fn (CrmLeadStatus $status): array => [
                'name' => $status->name,
                'tone' => $status->tone,
                'count' => (clone $base)->where('status_id', $status->id)->count(),
            ]);

        $sourceBreakdown = $this->sourcesForCompany($user->company_id)
            ->map(fn (CrmLeadSource $source): array => [
                'name' => $source->name,
                'tone' => $source->tone,
                'count' => (clone $base)->where('source_id', $source->id)->count(),
            ]);

        $activityQuery = CrmActivity::query()
            ->where('company_id', $user->company_id)
            ->whereNull('completed_at')
            ->when($this->isSales($user), fn (Builder $query) => $query->where('assigned_user_id', $user->id));

        return [
            'total_leads' => (clone $base)->count(),
            'new_leads' => $stageCount(LeadStageType::New),
            'qualified_leads' => $stageCount(LeadStageType::Qualified),
            'demo_scheduled' => $stageCount(LeadStageType::DemoScheduled),
            'won_leads' => $stageCount(LeadStageType::Won),
            'lost_leads' => $stageCount(LeadStageType::Lost),
            'pipeline_value' => (clone $base)->whereHas('status', fn (Builder $query) => $query->where('is_lost', false))->sum('expected_value'),
            'overdue_followups' => (clone $activityQuery)->where('scheduled_at', '<', now())->count(),
            'leads_by_status' => $statusBreakdown,
            'leads_by_source' => $sourceBreakdown,
            'recent_leads' => $this->queryForUser($user)->limit(6)->get(),
            'upcoming_activities' => (clone $activityQuery)->with(['lead', 'assignedUser'])->where('scheduled_at', '>=', now()->startOfDay())->oldest('scheduled_at')->limit(6)->get(),
        ];
    }

    /**
     * @return Collection<int, CrmLeadSource>
     */
    public function sourcesForCompany(int $companyId): Collection
    {
        return CrmLeadSource::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, CrmLeadStatus>
     */
    public function statusesForCompany(int $companyId): Collection
    {
        return CrmLeadStatus::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, CrmTag>
     */
    public function tagsForCompany(int $companyId): Collection
    {
        return CrmTag::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function queryForUser(User $user, bool $withTrashed = false): Builder
    {
        return $this->baseQueryForUser($user, $withTrashed)
            ->with(['source', 'status', 'assignedUser', 'crmCompany', 'contact', 'tags']);
    }

    private function baseQueryForUser(User $user, bool $withTrashed = false): Builder
    {
        return CrmLead::query()
            ->where('company_id', $user->company_id)
            ->when($withTrashed, fn (Builder $query) => $query->withTrashed())
            ->when($this->isSales($user), function (Builder $query) use ($user): void {
                $query->where(function (Builder $query) use ($user): void {
                    $query->where('assigned_user_id', $user->id)
                        ->orWhere('created_by', $user->id);
                });
            });
    }

    private function isSales(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Sales;
    }
}
