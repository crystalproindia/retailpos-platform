<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkforceEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceBreakTest extends TestCase
{
    use RefreshDatabase;
    public function test_employee_can_only_have_one_active_break_and_must_end_it_before_normal_checkout(): void
    {
        $company = Company::factory()->create(); $branch = Branch::factory()->for($company)->create(['is_active' => true]); $employee = WorkforceEmployee::create(['company_id' => $company->id, 'primary_branch_id' => $branch->id, 'employee_number' => 'BR-1', 'first_name' => 'Break', 'display_name' => 'Break User', 'status' => 'active']); $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'workforce_employee_id' => $employee->id, 'role' => UserRole::Staff, 'account_status' => 'active']); BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $user->id]);
        $record = AttendanceRecord::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'user_id' => $user->id, 'outlet_id' => $branch->id, 'attendance_date' => now()->toDateString(), 'checked_in_at' => now()->subHour(), 'attendance_state' => 'checked_in', 'attendance_status' => 'present']);
        $this->actingAs($user)->post(route('attendance.breaks.start', $record), ['break_type' => 'meal'])->assertRedirect();
        $this->actingAs($user)->post(route('attendance.breaks.start', $record), ['break_type' => 'meal'])->assertSessionHasErrors('break');
        $this->actingAs($user)->post(route('attendance.check-out', $record))->assertSessionHasErrors('break');
    }
}
