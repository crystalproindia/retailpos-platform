<?php

namespace App\Services\Tasks;

use App\Enums\Tasks\TaskType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Tasks\Task;
use App\Models\User;
use App\Models\WorkforceEmployee;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TaskAccessService
{
    public function __construct(private readonly OutletAccessService $outlets) {}

    public function canView(User $user, Task $task): bool
    {
        if ($task->company_id !== $user->company_id) {
            return false;
        }

        if ($task->task_type === TaskType::Personal) {
            return $task->owner_user_id === $user->id;
        }

        if (in_array($user->id, array_filter([$task->owner_user_id, $task->assigned_user_id, $task->created_by_user_id]), true)) {
            return true;
        }

        if ($this->outlets->hasCompanyWideAccess($user)) {
            return $user->can('tasks.view_team');
        }

        return $user->can('tasks.view_team')
            && $task->outlet_id !== null
            && $this->outlets->accessibleOutlets($user)->contains('id', $task->outlet_id);
    }

    public function canManage(User $user, Task $task): bool
    {
        if (! $this->canView($user, $task)) {
            return false;
        }

        if ($task->task_type === TaskType::Personal) {
            return $task->owner_user_id === $user->id && $user->can('tasks.update_own');
        }

        if (in_array($user->id, array_filter([$task->owner_user_id, $task->assigned_user_id]), true)) {
            return $user->can('tasks.update_own');
        }

        return $user->can('tasks.manage_team');
    }

    public function assertCanView(User $user, Task $task): void
    {
        abort_unless($this->canView($user, $task), 403);
    }

    public function assertCanManage(User $user, Task $task): void
    {
        abort_unless($this->canManage($user, $task), 403);
    }

    public function assertCanCreateWork(User $user): void
    {
        abort_unless($user->can('tasks.create_work'), 403);
    }

    public function assertCanAssign(User $user, ?int $outletId, User $assignee): void
    {
        abort_unless($user->can('tasks.assign'), 403);

        if ($assignee->company_id !== $user->company_id || ! $assignee->is_active || $assignee->account_status === 'suspended') {
            throw ValidationException::withMessages(['assigned_user_id' => 'Choose an active user in this company.']);
        }

        if ($assignee->workforce_employee_id && ! WorkforceEmployee::query()
            ->where('company_id', $user->company_id)
            ->whereKey($assignee->workforce_employee_id)
            ->whereIn('status', ['active', 'on_leave'])
            ->exists()) {
            throw ValidationException::withMessages(['assigned_user_id' => 'Choose a user linked to an active employee profile.']);
        }

        if (! $outletId) {
            if (! $this->outlets->hasCompanyWideAccess($user)) {
                throw ValidationException::withMessages(['outlet_id' => 'A work task assigned by a manager must be linked to an authorized outlet.']);
            }

            return;
        }

        $outlet = Branch::query()->where('company_id', $user->company_id)->find($outletId);
        if (! $outlet || ! $this->outlets->canAccess($user, $outlet) || ! $this->outlets->canAccess($assignee, $outlet)) {
            throw ValidationException::withMessages(['assigned_user_id' => 'The assignee must be active and authorized for this outlet.']);
        }
    }

    public function canReceiveSystemTask(User $assignee, int $companyId, ?int $outletId): bool
    {
        if ($assignee->company_id !== $companyId || ! $assignee->is_active || $assignee->account_status === 'suspended') {
            return false;
        }

        if ($assignee->workforce_employee_id && ! WorkforceEmployee::query()
            ->where('company_id', $companyId)
            ->whereKey($assignee->workforce_employee_id)
            ->whereIn('status', ['active', 'on_leave'])
            ->exists()) {
            return false;
        }

        if (! $outletId) {
            return true;
        }

        $outlet = Branch::query()->where('company_id', $companyId)->find($outletId);

        return $outlet !== null && $this->outlets->canAccess($assignee, $outlet);
    }

    public function assertCanLinkRecord(User $user, Model $record, ?int $outletId): void
    {
        if ($record->company_id !== $user->company_id) {
            throw ValidationException::withMessages(['related_id' => 'That record is not available in this company.']);
        }

        if ($outletId) {
            $outlet = Branch::query()->where('company_id', $user->company_id)->find($outletId);
            if (! $outlet || ! $this->outlets->canAccess($user, $outlet)) {
                throw ValidationException::withMessages(['outlet_id' => 'That outlet is not available to you.']);
            }
        }
    }

    public function isManager(User $user): bool
    {
        return ($user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role)) === UserRole::Manager;
    }
}
