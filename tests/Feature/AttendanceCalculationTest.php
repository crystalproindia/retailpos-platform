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

class AttendanceCalculationTest extends TestCase
{
    use RefreshDatabase;
    public function test_calculator_applies_late_early_break_and_overtime_from_server_timestamps(): void
    {
        $employee = WorkforceEmployee::factory()->create(['status' => 'active']); $shift = ShiftTemplate::create(['company_id' => $employee->company_id, 'name' => 'Day', 'code' => 'DAY', 'start_time' => '09:00', 'end_time' => '17:00', 'grace_after_minutes' => 10, 'grace_before_minutes' => 10, 'standard_work_minutes' => 480, 'overtime_after_minutes' => 480, 'is_active' => true]); $assignment = ShiftAssignment::create(['company_id' => $employee->company_id, 'employee_id' => $employee->id, 'outlet_id' => $employee->primary_branch_id, 'shift_template_id' => $shift->id, 'work_date' => '2026-08-03']);
        $record = AttendanceRecord::create(['company_id' => $employee->company_id, 'employee_id' => $employee->id, 'attendance_date' => '2026-08-03', 'shift_assignment_id' => $assignment->id, 'scheduled_start_at' => CarbonImmutable::parse('2026-08-03 09:00', 'Asia/Kolkata')->utc(), 'scheduled_end_at' => CarbonImmutable::parse('2026-08-03 17:00', 'Asia/Kolkata')->utc(), 'checked_in_at' => CarbonImmutable::parse('2026-08-03 09:15', 'Asia/Kolkata')->utc(), 'checked_out_at' => CarbonImmutable::parse('2026-08-03 18:15', 'Asia/Kolkata')->utc()]);
        app(AttendanceCalculator::class)->recalculate($record); $this->assertSame(5, $record->late_minutes); $this->assertSame(0, $record->early_departure_minutes); $this->assertSame(540, $record->worked_minutes); $this->assertSame(60, $record->overtime_minutes);
    }
}
