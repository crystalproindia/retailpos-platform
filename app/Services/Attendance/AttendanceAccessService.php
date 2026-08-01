<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\WorkforceEmployee;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AttendanceAccessService
{
    public function __construct(private readonly OutletAccessService $outlets) {}

    public function employeeFor(User $user): WorkforceEmployee
    {
        $employee = $user->employee;
        if (! $employee || $employee->company_id !== $user->company_id || $employee->status !== 'active') {
            throw ValidationException::withMessages(['attendance' => 'An active employee profile is required for attendance actions.']);
        }

        return $employee;
    }

    public function canManageEmployee(User $actor, WorkforceEmployee $employee): bool
    {
        return $employee->company_id === $actor->company_id
            && $actor->can('attendance.manage_team')
            && ($this->outlets->hasCompanyWideAccess($actor)
                || ($employee->primaryBranch && $this->outlets->canAccess($actor, $employee->primaryBranch)));
    }

    public function assertManageEmployee(User $actor, WorkforceEmployee $employee): void
    {
        if (! $this->canManageEmployee($actor, $employee)) {
            abort(404);
        }
    }

    public function assertOutlet(User $actor, Branch $outlet): void
    {
        if (! $this->outlets->canAccess($actor, $outlet)) {
            throw ValidationException::withMessages(['outlet_id' => 'That outlet is not available to you.']);
        }
    }

    /** @return Builder<AttendanceRecord> */
    public function attendanceQuery(User $actor): Builder
    {
        $query = AttendanceRecord::query()->where('company_id', $actor->company_id);
        if (! $this->outlets->hasCompanyWideAccess($actor)) {
            $query->whereIn('outlet_id', $this->outlets->accessibleOutlets($actor)->pluck('id'));
        }

        return $query;
    }
}
