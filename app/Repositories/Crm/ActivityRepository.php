<?php

namespace App\Repositories\Crm;

use App\Enums\UserRole;
use App\Models\Crm\CrmActivity;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ActivityRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->queryForUser($user, ($filters['trashed'] ?? null) === 'with')
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                match ($status) {
                    'completed' => $query->whereNotNull('completed_at'),
                    'overdue' => $query->whereNull('completed_at')->where('scheduled_at', '<', now()),
                    default => $query->whereNull('completed_at'),
                };
            })
            ->latest('scheduled_at')
            ->paginate(10)
            ->withQueryString();
    }

    public function findForUser(User $user, int $activityId, bool $withTrashed = false): CrmActivity
    {
        return $this->queryForUser($user, $withTrashed)->findOrFail($activityId);
    }

    /**
     * @return Collection<int, CrmActivity>
     */
    public function upcomingForUser(User $user, int $limit = 8): Collection
    {
        return $this->queryForUser($user)
            ->whereNull('completed_at')
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->oldest('scheduled_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CrmActivity>
     */
    public function followUpsForUser(User $user, bool $overdueOnly = false): Collection
    {
        return $this->queryForUser($user)
            ->whereIn('type', ['follow_up', 'call', 'meeting', 'task'])
            ->whereNull('completed_at')
            ->when($overdueOnly, fn (Builder $query) => $query->where('scheduled_at', '<', now()))
            ->oldest('scheduled_at')
            ->limit(50)
            ->get();
    }

    private function queryForUser(User $user, bool $withTrashed = false): Builder
    {
        return CrmActivity::query()
            ->with(['lead.status', 'crmCompany', 'contact', 'assignedUser'])
            ->where('company_id', $user->company_id)
            ->when($withTrashed, fn (Builder $query) => $query->withTrashed())
            ->when($this->isSales($user), fn (Builder $query) => $query->where('assigned_user_id', $user->id));
    }

    private function isSales(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Sales;
    }
}
