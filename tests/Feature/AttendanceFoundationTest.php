<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\ShiftAssignment;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\WorkforceEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_employee_can_check_in_once_and_check_out_with_server_calculated_totals(): void
    {
        [$user, $employee, $branch] = $this->employee();
        $shift = ShiftTemplate::create(['company_id' => $user->company_id, 'name' => 'General', 'code' => 'GEN', 'start_time' => '09:00', 'end_time' => '17:00', 'standard_work_minutes' => 480, 'overtime_after_minutes' => 480, 'is_active' => true]);
        ShiftAssignment::create(['company_id' => $user->company_id, 'employee_id' => $employee->id, 'outlet_id' => $branch->id, 'shift_template_id' => $shift->id, 'work_date' => now($branch->timezone ?: 'UTC')->toDateString(), 'assigned_by' => $user->id]);

        $this->actingAs($user)->post(route('attendance.check-in'))->assertRedirect();
        $record = AttendanceRecord::query()->sole();
        $this->assertSame('checked_in', $record->attendance_state);
        $this->assertSame('present', $record->attendance_status);
        $this->actingAs($user)->post(route('attendance.check-in'))->assertSessionHasErrors('attendance');
        $this->actingAs($user)->post(route('attendance.check-out', $record))->assertRedirect()->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame('completed', $record->attendance_state);
        $this->assertNotNull($record->checked_out_at);
        $this->assertGreaterThanOrEqual(0, $record->worked_minutes);
    }

    public function test_tenant_cannot_access_another_tenant_attendance_record(): void
    {
        [$user] = $this->employee();
        [, $otherEmployee] = $this->employee('other');
        $record = AttendanceRecord::create(['company_id' => $otherEmployee->company_id, 'employee_id' => $otherEmployee->id, 'attendance_date' => now()->toDateString(), 'attendance_status' => 'present']);
        $this->actingAs($user)->post(route('attendance.check-out', $record))->assertNotFound();
    }

    public function test_unlinked_user_sees_safe_attendance_state_without_controls(): void
    {
        $company = Company::factory()->create(); $branch = Branch::factory()->for($company)->create(['is_active' => true]);
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Staff, 'account_status' => 'active']);
        $this->actingAs($user)->get(route('attendance.self'))->assertOk()->assertSee('not linked yet');
    }

    public function test_correction_request_rejects_a_check_out_before_check_in(): void
    {
        [$user, $employee, $branch] = $this->employee();
        $record = AttendanceRecord::create([
            'company_id' => $user->company_id,
            'employee_id' => $employee->id,
            'outlet_id' => $branch->id,
            'attendance_date' => '2026-08-02',
            'checked_in_at' => '2026-08-02 09:00:00',
            'checked_out_at' => '2026-08-02 17:00:00',
            'attendance_status' => 'present',
            'attendance_state' => 'completed',
        ]);

        $this->actingAs($user)->post(route('attendance.corrections.store', $record), [
            'checked_in_at' => '2026-08-02 10:00:00',
            'checked_out_at' => '2026-08-02 09:00:00',
            'reason' => 'Corrected terminal entry.',
        ])->assertSessionHasErrors('checked_out_at');
    }

    public function test_missing_checkout_is_flagged_once_and_does_not_block_the_next_attendance_day(): void
    {
        [$user, $employee, $branch] = $this->employee();
        $yesterday = now($branch->timezone)->subDay()->toDateString();
        $record = AttendanceRecord::create([
            'company_id' => $user->company_id,
            'employee_id' => $employee->id,
            'outlet_id' => $branch->id,
            'attendance_date' => $yesterday,
            'checked_in_at' => now($branch->timezone)->subDay()->setTime(9, 0)->utc(),
            'attendance_status' => 'present',
            'attendance_state' => 'checked_in',
        ]);

        $this->artisan('attendance:mark-missing-checkouts', ['--company' => $user->company_id])->assertExitCode(0);
        $this->assertSame('missing_check_out', $record->refresh()->attendance_status);
        $this->assertSame(1, AuditLog::query()->where('event', 'attendance.missing_checkout.flagged')->count());
        $this->artisan('attendance:mark-missing-checkouts', ['--company' => $user->company_id])->assertExitCode(0);
        $this->assertSame(1, AuditLog::query()->where('event', 'attendance.missing_checkout.flagged')->count());

        $this->actingAs($user)->post(route('attendance.check-in'))->assertRedirect();
        $this->assertSame(2, AttendanceRecord::query()->where('employee_id', $employee->id)->count());
    }

    /** @return array{User, WorkforceEmployee, Branch} */
    private function employee(string $suffix = ''): array
    {
        $company = Company::factory()->create(); $branch = Branch::factory()->for($company)->create(['is_active' => true, 'timezone' => 'Asia/Kolkata']);
        $employee = WorkforceEmployee::create(['company_id' => $company->id, 'primary_branch_id' => $branch->id, 'employee_number' => 'AT'.$company->id, 'first_name' => 'Asha', 'display_name' => 'Asha '.$suffix, 'status' => 'active']);
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'workforce_employee_id' => $employee->id, 'role' => UserRole::Staff, 'account_status' => 'active', 'is_active' => true]);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $user->id]);
        return [$user, $employee, $branch];
    }
}
