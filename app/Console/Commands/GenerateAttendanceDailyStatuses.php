<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Holiday;
use App\Models\WeeklyOff;
use App\Models\WorkforceEmployee;
use Illuminate\Console\Command;

class GenerateAttendanceDailyStatuses extends Command
{
    protected $signature = 'attendance:generate-daily-status {--company= : Limit to one tenant} {--date= : Local attendance date} {--limit=500 : Maximum employees} {--dry-run : Report without writing}';
    protected $description = 'Create holiday and weekly-off attendance records; it never labels an employee absent automatically.';

    public function handle(): int
    {
        $employees = WorkforceEmployee::query()->where('status', 'active')->when($this->option('company'), fn ($query, $company) => $query->where('company_id', $company))->with(['primaryBranch', 'user'])->limit((int) $this->option('limit'))->get(); $created = 0;
        foreach ($employees as $employee) { $date = $this->option('date') ?: now($employee->primaryBranch?->timezone ?: config('app.timezone'))->toDateString(); $holiday = Holiday::query()->where('company_id', $employee->company_id)->whereDate('holiday_date', $date)->where('is_active', true)->where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $employee->primary_branch_id))->exists(); $off = WeeklyOff::query()->where('company_id', $employee->company_id)->where('weekday', now($employee->primaryBranch?->timezone ?: config('app.timezone'))->dayOfWeekIso)->where('is_active', true)->where(fn ($q) => $q->where('employee_id', $employee->id)->orWhere(function ($nested) use ($employee): void { $nested->whereNull('employee_id')->where(function ($scope) use ($employee): void { $scope->whereNull('outlet_id')->orWhere('outlet_id', $employee->primary_branch_id); }); }))->exists(); if (! $holiday && ! $off) continue; if ($this->option('dry-run')) { $created++; continue; } AttendanceRecord::query()->firstOrCreate(['company_id' => $employee->company_id, 'employee_id' => $employee->id, 'attendance_date' => $date], ['user_id' => $employee->user?->id, 'outlet_id' => $employee->primary_branch_id, 'attendance_status' => $holiday ? 'holiday' : 'weekly_off', 'attendance_source' => 'system_generated', 'attendance_state' => 'completed']); $created++; }
        $this->table(['employees', 'created or already present', 'dry run'], [[$employees->count(), $created, $this->option('dry-run') ? 'yes' : 'no']]); return self::SUCCESS;
    }
}
