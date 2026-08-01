<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class MarkMissingAttendanceCheckouts extends Command
{
    protected $signature = 'attendance:mark-missing-checkouts {--company= : Limit to one tenant} {--limit=250 : Maximum rows} {--dry-run : Report without writing}';
    protected $description = 'Mark stale active attendance sessions as missing check-outs without creating discipline or pay decisions.';

    public function handle(AuditLogger $audit): int
    {
        $query = AttendanceRecord::query()->whereNotNull('checked_in_at')->whereNull('checked_out_at')->whereDate('attendance_date', '<', now()->toDateString());
        if ($this->option('company')) $query->where('company_id', $this->option('company'));
        $records = $query->limit((int) $this->option('limit'))->get();
        if (! $this->option('dry-run')) foreach ($records as $record) { $record->update(['attendance_status' => 'missing_check_out', 'attendance_state' => 'checked_in']); $audit->record('attendance.missing_checkout.flagged', $record, 'Missing check-out flagged for review'); }
        $this->table(['examined', 'flagged', 'dry run'], [[$records->count(), $records->count(), $this->option('dry-run') ? 'yes' : 'no']]);
        return self::SUCCESS;
    }
}
