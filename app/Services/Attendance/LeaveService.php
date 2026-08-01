<?php

namespace App\Services\Attendance;

use App\Models\EmployeeLeaveBalance;
use App\Models\AttendanceRecord;
use App\Models\Holiday;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WeeklyOff;
use App\Models\WorkforceEmployee;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    public function __construct(private readonly AttendanceAccessService $access, private readonly AuditLogger $audit) {}

    public function createType(User $actor, array $data): LeaveType
    {
        return DB::transaction(function () use ($actor, $data): LeaveType {
            $type = LeaveType::create($data + ['company_id' => $actor->company_id]);
            $this->audit->record('leave.policy.created', $type, 'Leave policy created');
            return $type;
        });
    }

    public function adjustBalance(User $actor, WorkforceEmployee $employee, LeaveType $type, string $period, float $amount, string $reason): EmployeeLeaveBalance
    {
        $this->access->assertManageEmployee($actor, $employee);
        if ($type->company_id !== $actor->company_id || blank($reason)) throw ValidationException::withMessages(['balance' => 'A valid leave type and adjustment reason are required.']);
        return DB::transaction(function () use ($actor, $employee, $type, $period, $amount, $reason): EmployeeLeaveBalance {
            $balance = $this->balanceFor($employee, $type, $period, true);
            $balance->adjusted = round((float) $balance->adjusted + $amount, 2);
            $this->reconcile($balance);
            LeaveBalanceTransaction::create(['company_id' => $actor->company_id, 'employee_leave_balance_id' => $balance->id, 'entry_type' => 'adjustment', 'amount' => $amount, 'reason' => $reason, 'actor_user_id' => $actor->id]);
            $this->audit->record('leave.balance.adjusted', $balance, 'Leave balance adjusted');
            return $balance->refresh();
        });
    }

    public function request(User $actor, array $data): LeaveRequest
    {
        $employee = $this->access->employeeFor($actor);
        return $this->createRequest($actor, $employee, $data);
    }

    public function createRequest(User $actor, WorkforceEmployee $employee, array $data): LeaveRequest
    {
        if ($employee->id !== $actor->workforce_employee_id) $this->access->assertManageEmployee($actor, $employee);
        return DB::transaction(function () use ($actor, $employee, $data): LeaveRequest {
            $type = LeaveType::query()->where('company_id', $actor->company_id)->whereKey($data['leave_type_id'])->where('is_active', true)->firstOrFail();
            $starts = CarbonImmutable::parse($data['starts_on'], $this->timezone($employee, $actor))->startOfDay();
            $ends = CarbonImmutable::parse($data['ends_on'], $this->timezone($employee, $actor))->startOfDay();
            if ($ends->lessThan($starts)) throw ValidationException::withMessages(['ends_on' => 'The end date must not be before the start date.']);
            $existing = LeaveRequest::query()->where('company_id', $actor->company_id)->where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])->whereDate('starts_on', '<=', $ends)->whereDate('ends_on', '>=', $starts)->exists();
            if ($existing) throw ValidationException::withMessages(['starts_on' => 'This leave request overlaps an existing request.']);
            $days = $this->requestedDays($employee, $starts, $ends, $data['day_portion'] ?? 'full_day');
            if ($days <= 0) throw ValidationException::withMessages(['starts_on' => 'The selected dates are non-working days.']);
            $period = $starts->format('Y');
            $balance = $this->balanceFor($employee, $type, $period, true);
            $available = (float) $balance->remaining - (float) $balance->pending;
            if (! $type->negative_balance_allowed && $available + 0.0001 < $days) throw ValidationException::withMessages(['leave_type_id' => 'The available leave balance is insufficient.']);
            $request = LeaveRequest::create([
                'company_id' => $actor->company_id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'outlet_id' => $employee->primary_branch_id,
                'starts_on' => $starts->toDateString(), 'ends_on' => $ends->toDateString(), 'day_portion' => $data['day_portion'] ?? 'full_day', 'requested_days' => $days,
                'reason' => $data['reason'] ?? null, 'status' => $type->approval_required ? 'pending' : 'approved', 'requested_by' => $actor->id,
            ]);
            if ($request->status === 'approved') {
                $balance->used = round((float) $balance->used + $days, 2);
                LeaveBalanceTransaction::create(['company_id' => $actor->company_id, 'employee_leave_balance_id' => $balance->id, 'leave_request_id' => $request->id, 'entry_type' => 'approved_leave', 'amount' => -$days, 'reason' => 'Leave approved without review', 'actor_user_id' => $actor->id]);
            } else {
                $balance->pending = round((float) $balance->pending + $days, 2);
                LeaveBalanceTransaction::create(['company_id' => $actor->company_id, 'employee_leave_balance_id' => $balance->id, 'leave_request_id' => $request->id, 'entry_type' => 'pending_leave', 'amount' => -$days, 'reason' => 'Leave request pending', 'actor_user_id' => $actor->id]);
            }
            $this->reconcile($balance);
            if ($request->status === 'approved') $this->applyApprovedLeave($request);
            $this->audit->record('leave.requested', $request, 'Leave request submitted');
            return $request->refresh();
        });
    }

    public function review(User $actor, LeaveRequest $request, bool $approve, ?string $note = null): LeaveRequest
    {
        $this->access->assertManageEmployee($actor, $request->employee);
        if ($request->employee->user?->id === $actor->id) throw ValidationException::withMessages(['leave' => 'You cannot approve your own leave request.']);
        return DB::transaction(function () use ($actor, $request, $approve, $note): LeaveRequest {
            $request = LeaveRequest::query()->with(['employee', 'leaveType'])->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'pending') throw ValidationException::withMessages(['leave' => 'This leave request has already been reviewed.']);
            if (! $approve && blank($note)) throw ValidationException::withMessages(['review_note' => 'A rejection reason is required.']);
            $balance = $this->balanceFor($request->employee, $request->leaveType, $request->starts_on->format('Y'), true);
            $days = (float) $request->requested_days;
            $balance->pending = max(0, round((float) $balance->pending - $days, 2));
            if ($approve) {
                $balance->used = round((float) $balance->used + $days, 2);
                $entry = 'approved_leave';
            } else $entry = 'rejected_leave';
            $this->reconcile($balance);
            $request->update(['status' => $approve ? 'approved' : 'rejected', 'reviewed_by' => $actor->id, 'review_note' => $note, 'reviewed_at' => now()]);
            if ($approve) $this->applyApprovedLeave($request);
            LeaveBalanceTransaction::create(['company_id' => $actor->company_id, 'employee_leave_balance_id' => $balance->id, 'leave_request_id' => $request->id, 'entry_type' => $entry, 'amount' => $approve ? -$days : $days, 'reason' => $note, 'actor_user_id' => $actor->id]);
            $this->audit->record($approve ? 'leave.approved' : 'leave.rejected', $request, $approve ? 'Leave request approved' : 'Leave request rejected');
            return $request->refresh();
        });
    }

    public function withdraw(User $actor, LeaveRequest $request): LeaveRequest
    {
        if ($request->employee_id !== $this->access->employeeFor($actor)->id) abort(404);
        return DB::transaction(function () use ($actor, $request): LeaveRequest {
            $request = LeaveRequest::query()->with(['employee', 'leaveType'])->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'pending') throw ValidationException::withMessages(['leave' => 'Only pending requests can be withdrawn.']);
            $balance = $this->balanceFor($request->employee, $request->leaveType, $request->starts_on->format('Y'), true);
            $balance->pending = max(0, round((float) $balance->pending - (float) $request->requested_days, 2));
            $this->reconcile($balance);
            $request->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
            LeaveBalanceTransaction::create(['company_id' => $actor->company_id, 'employee_leave_balance_id' => $balance->id, 'leave_request_id' => $request->id, 'entry_type' => 'withdrawn_leave', 'amount' => (float) $request->requested_days, 'reason' => 'Leave withdrawn', 'actor_user_id' => $actor->id]);
            $this->audit->record('leave.withdrawn', $request, 'Leave request withdrawn');
            return $request->refresh();
        });
    }

    public function balanceFor(WorkforceEmployee $employee, LeaveType $type, string $period, bool $lock = false): EmployeeLeaveBalance
    {
        $query = EmployeeLeaveBalance::query()->where(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'period' => $period]);
        if ($lock) $query->lockForUpdate();
        $balance = $query->first();
        if (! $balance) {
            $balance = EmployeeLeaveBalance::create(['company_id' => $employee->company_id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'period' => $period, 'opening_balance' => $type->annual_entitlement, 'remaining' => $type->annual_entitlement]);
        }
        return $balance;
    }

    private function reconcile(EmployeeLeaveBalance $balance): void
    {
        $balance->remaining = round((float) $balance->opening_balance + (float) $balance->accrued + (float) $balance->adjusted - (float) $balance->used, 2);
        $balance->save();
    }

    private function requestedDays(WorkforceEmployee $employee, CarbonImmutable $starts, CarbonImmutable $ends, string $portion): float
    {
        if ($portion !== 'full_day') return 0.5;
        $days = 0.0;
        for ($day = $starts; $day->lessThanOrEqualTo($ends); $day = $day->addDay()) if (! $this->nonWorkingDay($employee, $day)) $days++;
        return $days;
    }

    private function nonWorkingDay(WorkforceEmployee $employee, CarbonImmutable $day): bool
    {
        $holiday = Holiday::query()->where('company_id', $employee->company_id)->whereDate('holiday_date', $day->toDateString())->where('is_active', true)->where(function ($query) use ($employee): void { $query->whereNull('outlet_id')->orWhere('outlet_id', $employee->primary_branch_id); })->exists();
        if ($holiday) return true;
        return WeeklyOff::query()->where('company_id', $employee->company_id)->where('weekday', $day->dayOfWeekIso)->where('is_active', true)->where(function ($query) use ($employee): void { $query->where('employee_id', $employee->id)->orWhere(function ($nested) use ($employee): void { $nested->whereNull('employee_id')->where('outlet_id', $employee->primary_branch_id); })->orWhere(function ($nested): void { $nested->whereNull('employee_id')->whereNull('outlet_id'); }); })->exists();
    }

    private function timezone(WorkforceEmployee $employee, User $actor): string
    {
        return $employee->primaryBranch?->timezone ?: $actor->company?->timezone ?: config('app.timezone');
    }

    private function applyApprovedLeave(LeaveRequest $request): void
    {
        $employee = $request->employee;
        $actor = $request->requester;
        $start = CarbonImmutable::parse($request->starts_on, $this->timezone($employee, $actor));
        $end = CarbonImmutable::parse($request->ends_on, $this->timezone($employee, $actor));
        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            if ($this->nonWorkingDay($employee, $day)) continue;
            $record = AttendanceRecord::query()->where('company_id', $request->company_id)->where('employee_id', $employee->id)->whereDate('attendance_date', $day->toDateString())->lockForUpdate()->first();
            if ($record && ($record->checked_in_at || $record->checked_out_at)) continue;
            AttendanceRecord::query()->updateOrCreate(
                ['company_id' => $request->company_id, 'employee_id' => $employee->id, 'attendance_date' => $day->toDateString()],
                ['user_id' => $employee->user?->id, 'outlet_id' => $request->outlet_id, 'attendance_status' => 'on_leave', 'attendance_source' => 'system_generated', 'attendance_state' => 'completed', 'notes' => 'Approved leave request #'.$request->id],
            );
        }
    }
}
