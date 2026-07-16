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
     * @return array{demos_today: int, upcoming_demos: int, overdue_demos: int}
     */
    public function dashboardMetrics(User $user): array
    {
        $base = $this->queryForUser($user)->whereIn('status', [
            DemoScheduleStatus::Scheduled->value,
            DemoScheduleStatus::Rescheduled->value,
        ]);

        return [
            'demos_today' => (clone $base)->whereDate('scheduled_date', today())->count(),
            'upcoming_demos' => (clone $base)->where('starts_at', '>', now())->count(),
            'overdue_demos' => (clone $base)->where('starts_at', '<', now())->count(),
        ];
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
