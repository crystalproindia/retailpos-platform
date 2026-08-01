<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\ShiftAssignment;
use App\Models\ShiftTemplate;
use App\Models\WorkforceEmployee;
use App\Services\Attendance\AttendanceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvernightShiftTest extends TestCase
{
    use RefreshDatabase;
    public function test_overnight_shift_uses_the_next_calendar_day_for_scheduled_end(): void
    {
        $employee = WorkforceEmployee::factory()->create(['status' => 'active']); $shift = ShiftTemplate::create(['company_id' => $employee->company_id, 'name' => 'Night', 'code' => 'NIGHT', 'start_time' => '22:00', 'end_time' => '06:00', 'crosses_midnight' => true, 'standard_work_minutes' => 480, 'overtime_after_minutes' => 480, 'is_active' => true]); $assignment = ShiftAssignment::create(['company_id' => $employee->company_id, 'employee_id' => $employee->id, 'outlet_id' => $employee->primary_branch_id, 'shift_template_id' => $shift->id, 'work_date' => '2026-08-03']); $record = AttendanceRecord::create(['company_id' => $employee->company_id, 'employee_id' => $employee->id, 'attendance_date' => '2026-08-03', 'shift_assignment_id' => $assignment->id, 'checked_in_at' => CarbonImmutable::parse('2026-08-03 22:00', 'Asia/Kolkata')->utc(), 'checked_out_at' => CarbonImmutable::parse('2026-08-04 06:00', 'Asia/Kolkata')->utc()]); app(AttendanceCalculator::class)->recalculate($record); $this->assertSame(480, $record->worked_minutes); $this->assertSame('completed', $record->attendance_state);
    }
}
