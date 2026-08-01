<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\ShiftAssignment;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\WorkforceEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftAssignmentTest extends TestCase
{
    use RefreshDatabase;
    public function test_manager_can_assign_one_shift_per_employee_day_and_duplicate_is_rejected(): void
    {
        $company = Company::factory()->create(); $branch = Branch::factory()->for($company)->create(['is_active' => true]); $manager = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager, 'account_status' => 'active']); BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $manager->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $manager->id]); $employee = WorkforceEmployee::create(['company_id' => $company->id, 'primary_branch_id' => $branch->id, 'employee_number' => 'SH-1', 'first_name' => 'Shift', 'display_name' => 'Shift User', 'status' => 'active']); $shift = ShiftTemplate::create(['company_id' => $company->id, 'name' => 'Morning', 'code' => 'MOR', 'start_time' => '09:00', 'end_time' => '17:00', 'standard_work_minutes' => 480, 'overtime_after_minutes' => 480, 'is_active' => true]);
        $payload = ['employee_id' => $employee->id, 'shift_template_id' => $shift->id, 'work_date' => now()->addDay()->toDateString()];
        $this->actingAs($manager)->post(route('attendance.assignments.store'), $payload)->assertRedirect(); $this->assertCount(1, ShiftAssignment::all());
        $this->actingAs($manager)->post(route('attendance.assignments.store'), $payload)->assertSessionHasErrors('work_date');
    }
}
