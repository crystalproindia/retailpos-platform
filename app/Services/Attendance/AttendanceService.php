<?php

namespace App\Services\Attendance;

use App\Models\AttendanceBreak;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\OvertimeReview;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkforceEmployee;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        private readonly AttendanceAccessService $access,
        private readonly AttendanceCalculator $calculator,
        private readonly AuditLogger $audit,
    ) {}

    public function checkIn(User $actor, ?WorkforceEmployee $employee = null, array $data = []): AttendanceRecord
    {
        $employee ??= $this->access->employeeFor($actor);
        if ($employee->id !== $actor->workforce_employee_id) $this->access->assertManageEmployee($actor, $employee);

        return DB::transaction(function () use ($actor, $employee, $data): AttendanceRecord {
            $employee = WorkforceEmployee::query()->lockForUpdate()->findOrFail($employee->id);
            if ($employee->status !== 'active') throw ValidationException::withMessages(['employee' => 'Archived or inactive employees cannot check in.']);
            $timezone = $this->timezone($employee, $actor);
            $localNow = isset($data['checked_in_at']) ? CarbonImmutable::parse($data['checked_in_at'], $timezone) : CarbonImmutable::now($timezone);
            $now = $localNow->utc();
            $active = AttendanceRecord::query()->where('company_id', $actor->company_id)->where('employee_id', $employee->id)->whereNotNull('checked_in_at')->whereNull('checked_out_at')->lockForUpdate()->first();
            if ($active) throw ValidationException::withMessages(['attendance' => 'This employee already has an active attendance session.']);
            $assignment = ShiftAssignment::query()->with(['shift', 'outlet'])->where('company_id', $actor->company_id)->where('employee_id', $employee->id)->whereDate('work_date', $localNow->toDateString())->first();
            $outlet = $assignment?->outlet_id ? $assignment->outlet : $employee->primaryBranch;
            if (! $outlet) throw ValidationException::withMessages(['outlet' => 'An outlet assignment is required for attendance.']);
            $this->access->assertOutlet($actor, $outlet);
            $record = AttendanceRecord::query()->where('company_id', $actor->company_id)->where('employee_id', $employee->id)->whereDate('attendance_date', $localNow->toDateString())->lockForUpdate()->first();
            if ($record && $record->checked_in_at) throw ValidationException::withMessages(['attendance' => 'This employee is already checked in for this attendance day.']);
            [$scheduledStart, $scheduledEnd] = $this->scheduledWindow($assignment, $localNow);
            $record ??= new AttendanceRecord(['company_id' => $actor->company_id, 'employee_id' => $employee->id, 'attendance_date' => $localNow->toDateString()]);
            $record->fill([
                'user_id' => $employee->user?->id,
                'outlet_id' => $outlet->id,
                'shift_assignment_id' => $assignment?->id,
                'scheduled_start_at' => $scheduledStart,
                'scheduled_end_at' => $scheduledEnd,
                'checked_in_at' => $now,
                'attendance_status' => 'present',
                'attendance_state' => 'checked_in',
                'attendance_source' => $employee->id === $actor->workforce_employee_id ? 'employee_self' : 'manager_entry',
                'check_in_method' => $data['method'] ?? 'web',
                'is_manual' => ! empty($data['is_manual']),
                'manually_entered_by' => ! empty($data['is_manual']) ? $actor->id : null,
                'notes' => $data['notes'] ?? null,
            ]);
            if ($record->is_manual && blank($record->notes)) throw ValidationException::withMessages(['notes' => 'A reason is required for manual attendance entry.']);
            $this->calculator->recalculate($record)->save();
            $this->audit->record('attendance.checked_in', $record, 'Attendance check-in recorded');
            return $record->refresh();
        });
    }

    public function checkOut(User $actor, AttendanceRecord $attendance, array $data = []): AttendanceRecord
    {
        $this->assertSelfOrManager($actor, $attendance);
        return DB::transaction(function () use ($actor, $attendance, $data): AttendanceRecord {
            $attendance = AttendanceRecord::query()->with('breaks', 'shiftAssignment.shift')->lockForUpdate()->findOrFail($attendance->id);
            if (! $attendance->checked_in_at) throw ValidationException::withMessages(['attendance' => 'Check-in is required before check-out.']);
            if ($attendance->checked_out_at) throw ValidationException::withMessages(['attendance' => 'This attendance session has already been checked out.']);
            $activeBreak = $attendance->breaks->firstWhere('ended_at', null);
            if ($activeBreak && empty($data['override_active_break'])) throw ValidationException::withMessages(['break' => 'End the active break before checking out.']);
            if ($activeBreak) {
                if (blank($data['notes'] ?? null)) throw ValidationException::withMessages(['notes' => 'A reason is required to override an active break.']);
                $activeBreak->update(['ended_at' => now(), 'ended_by' => $actor->id, 'notes' => trim(($activeBreak->notes ? $activeBreak->notes."\n" : '').$data['notes'])]);
                $activeBreak->update(['duration_minutes' => max(0, $activeBreak->started_at->diffInMinutes($activeBreak->ended_at))]);
                $attendance->load('breaks');
            }
            $timezone = $this->timezone($attendance->employee, $actor);
            $checkedOut = (isset($data['checked_out_at']) ? CarbonImmutable::parse($data['checked_out_at'], $timezone) : CarbonImmutable::now($timezone))->utc();
            if ($checkedOut->lessThan($attendance->checked_in_at)) throw ValidationException::withMessages(['checked_out_at' => 'Check-out cannot be before check-in.']);
            $attendance->fill(['checked_out_at' => $checkedOut, 'check_out_method' => $data['method'] ?? 'web']);
            $this->calculator->recalculate($attendance)->save();
            $this->ensureOvertimeReview($attendance);
            $this->audit->record('attendance.checked_out', $attendance, 'Attendance check-out recorded');
            return $attendance->refresh();
        });
    }

    public function startBreak(User $actor, AttendanceRecord $attendance, string $type = 'short_break', ?string $notes = null): AttendanceBreak
    {
        $this->assertSelfOrManager($actor, $attendance);
        return DB::transaction(function () use ($actor, $attendance, $type, $notes): AttendanceBreak {
            $attendance = AttendanceRecord::query()->with('breaks')->lockForUpdate()->findOrFail($attendance->id);
            if (! $attendance->checked_in_at || $attendance->checked_out_at) throw ValidationException::withMessages(['attendance' => 'Breaks are available only during an active attendance session.']);
            if ($attendance->breaks->contains(fn (AttendanceBreak $break) => $break->ended_at === null)) throw ValidationException::withMessages(['break' => 'Only one active break is allowed.']);
            $break = AttendanceBreak::create(['company_id' => $attendance->company_id, 'attendance_id' => $attendance->id, 'employee_id' => $attendance->employee_id, 'started_at' => now(), 'break_type' => $type, 'created_by' => $actor->id, 'notes' => $notes]);
            $this->audit->record('attendance.break.started', $break, 'Attendance break started');
            return $break;
        });
    }

    public function endBreak(User $actor, AttendanceBreak $break, ?string $notes = null): AttendanceBreak
    {
        $attendance = $break->attendance;
        $this->assertSelfOrManager($actor, $attendance);
        return DB::transaction(function () use ($actor, $break, $notes): AttendanceBreak {
            $break = AttendanceBreak::query()->lockForUpdate()->findOrFail($break->id);
            if ($break->ended_at) throw ValidationException::withMessages(['break' => 'This break has already ended.']);
            $break->update(['ended_at' => now(), 'ended_by' => $actor->id, 'duration_minutes' => $break->started_at->diffInMinutes(now()), 'notes' => $notes ?: $break->notes]);
            $attendance = AttendanceRecord::query()->with('breaks', 'shiftAssignment.shift')->lockForUpdate()->findOrFail($break->attendance_id);
            $this->calculator->recalculate($attendance)->save();
            $this->audit->record('attendance.break.ended', $break, 'Attendance break ended');
            return $break->refresh();
        });
    }

    public function requestCorrection(User $actor, AttendanceRecord $attendance, array $requested, string $reason): AttendanceCorrection
    {
        if ($attendance->employee_id !== $actor->workforce_employee_id) $this->access->assertManageEmployee($actor, $attendance->employee);
        return DB::transaction(function () use ($actor, $attendance, $requested, $reason): AttendanceCorrection {
            $attendance = AttendanceRecord::query()->lockForUpdate()->findOrFail($attendance->id);
            if ($attendance->correction_status === 'pending') throw ValidationException::withMessages(['attendance' => 'A correction is already awaiting review.']);
            $original = $attendance->only(['checked_in_at', 'checked_out_at', 'outlet_id', 'attendance_status', 'notes']);
            $correction = AttendanceCorrection::create(['company_id' => $attendance->company_id, 'attendance_id' => $attendance->id, 'employee_id' => $attendance->employee_id, 'requested_by' => $actor->id, 'original_values' => $original, 'requested_values' => $requested, 'reason' => $reason]);
            $attendance->update(['correction_status' => 'pending', 'attendance_status' => 'pending_correction']);
            $this->audit->record('attendance.correction.requested', $correction, 'Attendance correction requested');
            return $correction;
        });
    }

    public function reviewCorrection(User $actor, AttendanceCorrection $correction, bool $approve, ?string $note = null): AttendanceCorrection
    {
        $this->access->assertManageEmployee($actor, $correction->employee);
        return DB::transaction(function () use ($actor, $correction, $approve, $note): AttendanceCorrection {
            $correction = AttendanceCorrection::query()->with('attendance.breaks', 'attendance.shiftAssignment.shift')->lockForUpdate()->findOrFail($correction->id);
            if ($correction->status !== 'pending') throw ValidationException::withMessages(['correction' => 'This correction has already been reviewed.']);
            if (! $approve && blank($note)) throw ValidationException::withMessages(['review_note' => 'A reason is required when rejecting a correction.']);
            $correction->update(['status' => $approve ? 'approved' : 'rejected', 'reviewed_by' => $actor->id, 'review_note' => $note, 'reviewed_at' => now()]);
            $attendance = $correction->attendance;
            if ($approve) {
                $values = array_intersect_key($correction->requested_values, array_flip(['checked_in_at', 'checked_out_at', 'outlet_id', 'attendance_status', 'notes']));
                $attendance->fill($values);
                $this->calculator->recalculate($attendance);
                $attendance->correction_status = 'approved';
                $attendance->save();
                $this->ensureOvertimeReview($attendance);
            } else {
                $attendance->update(['correction_status' => 'rejected', 'attendance_status' => $attendance->checked_out_at ? 'present' : 'missing_check_out']);
            }
            $this->audit->record($approve ? 'attendance.correction.approved' : 'attendance.correction.rejected', $correction, $approve ? 'Attendance correction approved' : 'Attendance correction rejected');
            return $correction->refresh();
        });
    }

    public function reviewOvertime(User $actor, OvertimeReview $review, string $status, int $minutes, ?string $reason): OvertimeReview
    {
        $this->access->assertManageEmployee($actor, $review->employee);
        if (! in_array($status, ['approved', 'rejected'], true) || ($status === 'rejected' && blank($reason))) throw ValidationException::withMessages(['overtime' => 'A rejection reason is required.']);
        if ($minutes < 0 || $minutes > $review->candidate_minutes) throw ValidationException::withMessages(['approved_minutes' => 'Approved overtime cannot exceed the calculated candidate minutes.']);
        $review->update(['status' => $status, 'approved_minutes' => $status === 'approved' ? $minutes : 0, 'reason' => $reason, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        $this->audit->record('attendance.overtime.reviewed', $review, 'Overtime evidence reviewed');
        return $review->refresh();
    }

    private function assertSelfOrManager(User $actor, AttendanceRecord $attendance): void
    {
        if ($attendance->company_id !== $actor->company_id) abort(404);
        if ($attendance->employee_id !== $actor->workforce_employee_id) $this->access->assertManageEmployee($actor, $attendance->employee);
    }

    /** @return array{?CarbonImmutable, ?CarbonImmutable} */
    private function scheduledWindow(?ShiftAssignment $assignment, CarbonImmutable $day): array
    {
        if (! $assignment?->shift) return [null, null];
        $shift = $assignment->shift;
        $start = CarbonImmutable::parse($assignment->work_date->toDateString().' '.$shift->start_time, $day->timezone);
        $end = CarbonImmutable::parse($assignment->work_date->toDateString().' '.$shift->end_time, $day->timezone);
        if ($shift->crosses_midnight || $end->lessThanOrEqualTo($start)) $end = $end->addDay();
        return [$start->utc(), $end->utc()];
    }

    private function timezone(WorkforceEmployee $employee, User $actor): string
    {
        return $employee->primaryBranch?->timezone ?: $actor->company?->timezone ?: config('app.timezone');
    }

    private function ensureOvertimeReview(AttendanceRecord $attendance): void
    {
        if ($attendance->overtime_minutes <= 0) return;
        OvertimeReview::query()->firstOrCreate(['attendance_id' => $attendance->id], ['company_id' => $attendance->company_id, 'employee_id' => $attendance->employee_id, 'candidate_minutes' => $attendance->overtime_minutes, 'status' => 'pending_review']);
    }
}
