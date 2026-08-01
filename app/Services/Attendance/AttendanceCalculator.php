<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use Carbon\CarbonInterface;

class AttendanceCalculator
{
    /**
     * Grace after scheduled start applies to late arrival. Grace before scheduled end
     * applies to early departure. Minutes always use actual elapsed seconds, making
     * the result safe for DST transitions.
     */
    public function recalculate(AttendanceRecord $attendance): AttendanceRecord
    {
        $attendance->loadMissing('breaks', 'shiftAssignment.shift');
        $breakMinutes = $attendance->breaks
            ->whereNotNull('ended_at')
            ->reject(fn ($break) => $break->break_type === 'official_duty')
            ->sum('duration_minutes');
        $attendance->total_break_minutes = (int) $breakMinutes;

        if (! $attendance->checked_in_at) {
            $attendance->worked_minutes = 0;
            $attendance->late_minutes = 0;
            $attendance->early_departure_minutes = 0;
            $attendance->overtime_minutes = 0;
            return $attendance;
        }

        $shift = $attendance->shiftAssignment?->shift;
        $start = $attendance->scheduled_start_at;
        $end = $attendance->scheduled_end_at;
        $worked = 0;
        if ($attendance->checked_out_at) {
            $worked = max(0, (int) floor($attendance->checked_in_at->diffInSeconds($attendance->checked_out_at, true) / 60) - $attendance->total_break_minutes);
        }
        $attendance->worked_minutes = $worked;
        $attendance->late_minutes = $start
            ? max(0, (int) floor($start->copy()->addMinutes($shift?->grace_after_minutes ?? 0)->diffInSeconds($attendance->checked_in_at, false) / 60))
            : 0;
        $attendance->early_departure_minutes = ($end && $attendance->checked_out_at)
            ? max(0, (int) floor($attendance->checked_out_at->diffInSeconds($end->copy()->subMinutes($shift?->grace_before_minutes ?? 0), false) / 60))
            : 0;
        $attendance->overtime_minutes = $attendance->checked_out_at
            ? max(0, $worked - ($shift?->overtime_after_minutes ?? $shift?->standard_work_minutes ?? 0))
            : 0;

        if (! $attendance->checked_out_at) {
            // An active session is present until the scheduled recovery job determines
            // that a historical check-out is genuinely missing.
            $attendance->attendance_status = 'present';
            $attendance->attendance_state = 'checked_in';
        } else {
            $minimum = $shift?->minimum_work_minutes ?? 0;
            $attendance->attendance_status = $minimum > 0 && $worked < $minimum ? 'partial_day' : 'present';
            $attendance->attendance_state = 'completed';
        }

        return $attendance;
    }
}
