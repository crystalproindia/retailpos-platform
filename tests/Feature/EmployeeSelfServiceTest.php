<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkforceEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_employee_can_view_only_their_self_service_profile_without_manager_notes(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create(['is_primary' => true]);
        $employee = WorkforceEmployee::create(['company_id' => $company->id, 'primary_branch_id' => $branch->id, 'employee_number' => 'SELF-001', 'first_name' => 'Nila', 'display_name' => 'Nila', 'manager_notes' => 'Private management context', 'status' => 'active']);
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'workforce_employee_id' => $employee->id, 'role' => UserRole::Staff, 'account_status' => 'active']);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $user->id]);

        $this->actingAs($user)->get(route('workforce.self'))
            ->assertOk()
            ->assertSee('Nila')
            ->assertDontSee('Private management context');
    }
}
