<?php

namespace App\Repositories\Crm;

use App\Enums\Crm\DemoScheduleStatus;
use App\Enums\UserRole;
use App\Models\Crm\CrmLead;
use App\Models\Crm\DemoSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DemoScheduleRepository
{
    /**
     * @return Collection<int, DemoSchedule>
     */
    public function forLead(User $user, CrmLead $lead): Collection
    {
        return $this->queryForUser($user)
            ->where('lead_id', $lead->id)
            ->with(['assignedTo', 'scheduledBy'])
            ->latest('starts_at')
            ->get();
    }

    public function findForUser(User $user, int $scheduleId): DemoSchedule
    {
        return $this->queryForUser($user)
            ->with(['lead.source', 'lead.status', 'assignedTo', 'scheduledBy'])
            ->findOrFail($scheduleId);
    }

    /**
     * @return array{scheduled_demos: int, demos_today: int, upcoming_demos: int, overdue_demos: int, completed_demos: int, cancelled_demos: int}
     */
    public function dashboardMetrics(User $user): array
    {
        $all = $this->queryForUser($user);
        $active = $this->activeQuery($user);

        return [
            'scheduled_demos' => (clone $active)->count(),
            'demos_today' => (clone $active)->whereDate('scheduled_date', today())->count(),
            'upcoming_demos' => (clone $active)->where('starts_at', '>', now())->count(),
            'overdue_demos' => (clone $active)->where('starts_at', '<', now())->count(),
            'completed_demos' => (clone $all)->where('status', DemoScheduleStatus::Completed->value)->count(),
            'cancelled_demos' => (clone $all)->where('status', DemoScheduleStatus::Cancelled->value)->count(),
        ];
    }

    /**
     * @return Collection<int, DemoSchedule>
     */
    public function upcomingForUser(User $user, int $limit = 6): Collection
    {
        return $this->activeQuery($user)
            ->where('starts_at', '>=', now())
            ->with(['lead', 'assignedTo'])
            ->oldest('starts_at')
            ->limit($limit)
            ->get();
    }

    private function activeQuery(User $user): Builder
    {
        return $this->queryForUser($user)->whereIn('status', [
            DemoScheduleStatus::Scheduled->value,
            DemoScheduleStatus::Rescheduled->value,
        ]);
    }

    private function queryForUser(User $user): Builder
    {
        return DemoSchedule::query()
            ->where('company_id', $user->company_id)
            ->when($this->isSales($user), fn (Builder $query) => $query->where('assigned_to', $user->id));
    }

    private function isSales(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Sales;
    }
}
