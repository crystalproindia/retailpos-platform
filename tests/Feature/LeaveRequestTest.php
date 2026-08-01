<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkforceEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;
    public function test_leave_request_reserves_balance_and_manager_approval_consumes_it_without_self_approval(): void
    {
        $company = Company::factory()->create(); $branch = Branch::factory()->for($company)->create(['is_active' => true, 'timezone' => 'Asia/Kolkata']);
        $employee = WorkforceEmployee::create(['company_id' => $company->id, 'primary_branch_id' => $branch->id, 'employee_number' => 'LV-1', 'first_name' => 'Lea', 'display_name' => 'Lea User', 'status' => 'active']);
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'workforce_employee_id' => $employee->id, 'role' => UserRole::Staff, 'account_status' => 'active']); $manager = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager, 'account_status' => 'active']);
        foreach ([$user, $manager] as $account) BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $account->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $manager->id]);
        $type = LeaveType::create(['company_id' => $company->id, 'name' => 'Casual', 'code' => 'CL', 'annual_entitlement' => 5, 'is_active' => true, 'approval_required' => true]); $date = now('Asia/Kolkata')->addWeek()->nextWeekday()->toDateString();
        $this->actingAs($user)->post(route('attendance.leave.store'), ['leave_type_id' => $type->id, 'starts_on' => $date, 'ends_on' => $date, 'day_portion' => 'full_day'])->assertRedirect();
        $leave = LeaveRequest::query()->sole(); $this->assertSame('pending', $leave->status); $this->assertSame('1.00', (string) $employee->leaveBalances()->sole()->pending);
        $this->actingAs($user)->post(route('attendance.leave.review', $leave), ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($manager)->post(route('attendance.leave.review', $leave), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $leave->refresh()->status); $this->assertSame('1.00', (string) $employee->leaveBalances()->sole()->used);
    }
}
