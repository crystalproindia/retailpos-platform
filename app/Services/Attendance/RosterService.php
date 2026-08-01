<?php

namespace App\Services\Attendance;

use App\Models\Holiday;
use App\Models\RosterPublication;
use App\Models\ShiftAssignment;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\WorkforceEmployee;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RosterService
{
    public function __construct(private readonly AttendanceAccessService $access, private readonly AuditLogger $audit) {}

    public function assign(User $actor, array $data): ShiftAssignment
    {
        $employee = WorkforceEmployee::query()->where('company_id', $actor->company_id)->findOrFail($data['employee_id']);
        $this->access->assertManageEmployee($actor, $employee);
        if ($employee->status !== 'active') throw ValidationException::withMessages(['employee_id' => 'Only active employees can receive shifts.']);
        $shift = ShiftTemplate::query()->where('company_id', $actor->company_id)->whereKey($data['shift_template_id'])->where('is_active', true)->firstOrFail();
        $outlet = $employee->primaryBranch;
        if (! $outlet) throw ValidationException::withMessages(['employee_id' => 'The employee has no primary outlet.']);
        $this->access->assertOutlet($actor, $outlet);
        if ($shift->applicable_outlet_id && $shift->applicable_outlet_id !== $outlet->id) throw ValidationException::withMessages(['shift_template_id' => 'This shift belongs to another outlet.']);
        $date = CarbonImmutable::parse($data['work_date'], $outlet->timezone ?: config('app.timezone'))->toDateString();
        if ($this->hasApprovedLeave($employee, $date)) throw ValidationException::withMessages(['work_date' => 'An approved leave request cannot be overridden by a shift assignment.']);
        if (Holiday::query()->where('company_id', $actor->company_id)->whereDate('holiday_date', $date)->where('is_active', true)->where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $outlet->id))->exists()) throw ValidationException::withMessages(['work_date' => 'This date is a holiday for the selected outlet.']);
        return DB::transaction(function () use ($actor, $employee, $shift, $outlet, $date, $data): ShiftAssignment {
            $assignment = ShiftAssignment::query()->withTrashed()->where('employee_id', $employee->id)->whereDate('work_date', $date)->lockForUpdate()->first();
            if ($assignment && ! $assignment->trashed()) throw ValidationException::withMessages(['work_date' => 'This employee already has a shift on that date.']);
            $assignment ??= new ShiftAssignment(['company_id' => $actor->company_id, 'employee_id' => $employee->id, 'work_date' => $date]);
            if ($assignment->trashed()) $assignment->restore();
            $assignment->fill(['outlet_id' => $outlet->id, 'shift_template_id' => $shift->id, 'assigned_by' => $actor->id, 'assignment_source' => $data['assignment_source'] ?? 'manual', 'status' => 'scheduled', 'notes' => $data['notes'] ?? null])->save();
            $this->audit->record('attendance.shift.assigned', $assignment, 'Shift assignment saved');
            return $assignment->refresh();
        });
    }

    public function publish(User $actor, int $outletId, string $weekStart): RosterPublication
    {
        $outlet = \App\Models\Branch::query()->where('company_id', $actor->company_id)->findOrFail($outletId);
        $this->access->assertOutlet($actor, $outlet);
        $week = CarbonImmutable::parse($weekStart, $outlet->timezone ?: config('app.timezone'))->startOfWeek()->toDateString();
        $publication = RosterPublication::query()->updateOrCreate(['company_id' => $actor->company_id, 'outlet_id' => $outletId, 'week_starts_on' => $week], ['published_at' => now(), 'published_by' => $actor->id]);
        $this->audit->record('attendance.roster.published', $publication, 'Weekly roster published');
        return $publication;
    }

    private function hasApprovedLeave(WorkforceEmployee $employee, string $date): bool
    {
        return \App\Models\LeaveRequest::query()->where('employee_id', $employee->id)->where('status', 'approved')->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->exists();
    }
}
