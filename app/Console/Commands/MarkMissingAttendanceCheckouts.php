<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Throwable;

class MarkMissingAttendanceCheckouts extends Command
{
    protected $signature = 'attendance:mark-missing-checkouts {--company= : Limit to one tenant} {--limit=250 : Maximum rows} {--dry-run : Report without writing}';
    protected $description = 'Mark stale active attendance sessions as missing check-outs without creating discipline or pay decisions.';

    public function handle(AuditLogger $audit): int
    {
        $query = AttendanceRecord::query()->whereNotNull('checked_in_at')->whereNull('checked_out_at')->where('attendance_status', '!=', 'missing_check_out')->with('employee.primaryBranch')->orderBy('attendance_date');
        if ($this->option('company')) $query->where('company_id', $this->option('company'));
        $records = $query->limit((int) $this->option('limit'))->get()->filter(fn (AttendanceRecord $record): bool => $record->attendance_date->lt(now($record->employee?->primaryBranch?->timezone ?: config('app.timezone'))->startOfDay()));
        if (! $this->option('dry-run')) foreach ($records as $record) { try { $record->update(['attendance_status' => 'missing_check_out', 'attendance_state' => 'checked_in']); $audit->record('attendance.missing_checkout.flagged', $record, 'Missing check-out flagged for review'); } catch (Throwable) { $this->warn('An attendance record could not be marked and will be retried.'); } }
        $this->table(['examined', 'flagged', 'dry run'], [[$records->count(), $records->count(), $this->option('dry-run') ? 'yes' : 'no']]);
        return self::SUCCESS;
    }
}
