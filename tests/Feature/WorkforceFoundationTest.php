<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Models\WorkforceEmployee;
use App\Models\WorkforceInvitation;
use App\Models\WorkforceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_an_employee_without_a_login_and_assign_active_resources(): void
    {
        [$admin, $branch] = $this->administrator();
        $warehouse = Warehouse::create(['company_id' => $admin->company_id, 'branch_id' => $branch->id, 'name' => 'Main Store', 'code' => 'MAIN', 'type' => 'store', 'country' => 'India', 'is_active' => true]);

        $this->actingAs($admin)->post(route('workforce.employees.store'), [
            'employee_number' => 'EMP-001', 'first_name' => 'Asha', 'display_name' => 'Asha Kumar', 'work_email' => 'asha@tenant.test',
            'primary_branch_id' => $branch->id, 'status' => 'active', 'outlet_ids' => [$branch->id], 'warehouse_ids' => [$warehouse->id],
        ])->assertRedirect();

        $employee = WorkforceEmployee::query()->where('employee_number', 'EMP-001')->sole();
        $this->assertNull($employee->user);
        $this->assertDatabaseHas('workforce_employee_outlet_assignments', ['employee_id' => $employee->id, 'branch_id' => $branch->id]);
        $this->assertDatabaseHas('workforce_employee_warehouse_assignments', ['employee_id' => $employee->id, 'warehouse_id' => $warehouse->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'workforce.employee.created', 'company_id' => $admin->company_id]);
    }

    public function test_employee_profiles_are_tenant_scoped(): void
    {
        [$admin] = $this->administrator();
        [$other] = $this->administrator();
        $employee = WorkforceEmployee::create(['company_id' => $other->company_id, 'employee_number' => 'OTHER-1', 'first_name' => 'Other', 'display_name' => 'Other Employee', 'status' => 'active']);

        $this->actingAs($admin)->get(route('workforce.employees.show', $employee))->assertNotFound();
        $this->actingAs($admin)->put(route('workforce.employees.update', $employee), $this->employeePayload())->assertNotFound();
    }

    public function test_invitation_creates_pending_account_with_hashed_single_use_token_and_can_be_accepted(): void
    {
        [$admin, $branch] = $this->administrator();
        $employee = WorkforceEmployee::create(['company_id' => $admin->company_id, 'primary_branch_id' => $branch->id, 'employee_number' => 'EMP-002', 'first_name' => 'Ravi', 'display_name' => 'Ravi Singh', 'work_email' => 'ravi@tenant.test', 'status' => 'draft']);

        $this->actingAs($admin)->post(route('workforce.invitations.store', $employee), [
            'name' => 'Ravi Singh', 'email' => 'ravi@tenant.test', 'branch_id' => $branch->id, 'role' => UserRole::Staff->value,
        ])->assertRedirect();

        $invitation = WorkforceInvitation::query()->where('employee_id', $employee->id)->sole();
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertFalse(str_contains($invitation->token_hash, 'ravi'));
        $this->assertSame('pending_invitation', $invitation->user->account_status);
        $this->assertFalse($invitation->user->is_active);

        $rawToken = 'known-token-for-acceptance';
        $invitation->update(['token_hash' => hash('sha256', $rawToken)]);
        $this->post(route('workforce.invitation.accept', ['token' => $rawToken]), ['password' => 'a-strong-password', 'password_confirmation' => 'a-strong-password'])
            ->assertRedirect(route('login'));

        $this->assertNotNull($invitation->refresh()->accepted_at);
        $this->assertTrue($invitation->user->refresh()->is_active);
        $this->assertSame('active', $invitation->user->account_status);
        $this->post(route('workforce.invitation.accept', ['token' => $rawToken]), ['password' => 'another-strong-password', 'password_confirmation' => 'another-strong-password'])
            ->assertSessionHasErrors('invitation');
    }

    public function test_last_company_administrator_cannot_be_suspended_or_disabled(): void
    {
        [$admin] = $this->administrator();

        $this->actingAs($admin)->post(route('workforce.users.state', $admin), ['state' => 'disabled'])
            ->assertSessionHasErrors('user');
        $this->assertTrue($admin->refresh()->is_active);
        $this->assertSame('active', $admin->account_status);
    }

    public function test_custom_role_uses_explicit_permissions_and_cannot_be_created_as_administrator(): void
    {
        [$admin] = $this->administrator();

        $this->actingAs($admin)->post(route('workforce.roles.store'), [
            'name' => 'Reports reader', 'base_role' => 'staff', 'permissions' => ['crm.reports.view'],
        ])->assertRedirect();
        $role = WorkforceRole::query()->where('name', 'Reports reader')->sole();
        $user = User::factory()->for($admin->company)->create(['branch_id' => $admin->branch_id, 'role' => UserRole::Staff, 'workforce_role_id' => $role->id, 'is_active' => true]);
        $this->assertTrue($user->can('crm.reports.view'));
        $this->assertFalse($user->can('workforce.manage'));

        $this->actingAs($admin)->post(route('workforce.roles.store'), ['name' => 'Unsafe', 'base_role' => 'administrator'])
            ->assertSessionHasErrors('base_role');
    }

    public function test_outlet_manager_cannot_view_employee_outside_assigned_outlet(): void
    {
        [$admin, $primary] = $this->administrator();
        $secondary = Branch::factory()->for($admin->company)->create(['is_active' => true]);
        $manager = User::factory()->for($admin->company)->create(['branch_id' => $primary->id, 'role' => UserRole::Manager, 'is_active' => true]);
        BranchUserAssignment::create(['company_id' => $admin->company_id, 'branch_id' => $primary->id, 'user_id' => $manager->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $admin->id]);
        $employee = WorkforceEmployee::create(['company_id' => $admin->company_id, 'primary_branch_id' => $secondary->id, 'employee_number' => 'EMP-003', 'first_name' => 'Kiran', 'display_name' => 'Kiran Patel', 'status' => 'active']);

        $this->actingAs($manager)->get(route('workforce.employees.show', $employee))->assertNotFound();
    }

    public function test_employee_export_neutralizes_spreadsheet_formulas(): void
    {
        [$admin, $branch] = $this->administrator();
        WorkforceEmployee::create(['company_id' => $admin->company_id, 'primary_branch_id' => $branch->id, 'employee_number' => '=FORMULA', 'first_name' => 'Safe', 'display_name' => '=Export Value', 'status' => 'active']);

        $csv = $this->actingAs($admin)->get(route('workforce.employees.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString("'=FORMULA", $csv->streamedContent());
        $this->assertStringContainsString("'=Export Value", $csv->streamedContent());
    }

    public function test_existing_users_without_employee_profiles_can_open_a_safe_self_service_state(): void
    {
        [$admin] = $this->administrator();

        $this->actingAs($admin)->get(route('workforce.self'))
            ->assertOk()
            ->assertSee('Your employee profile is not linked yet');
    }

    public function test_administrator_can_render_user_accounts_without_assuming_role_object_shape(): void
    {
        [$admin] = $this->administrator();

        $this->actingAs($admin)->get(route('workforce.users.index'))
            ->assertOk()
            ->assertSee($admin->email)
            ->assertSee('Administrator');
    }

    public function test_administrator_can_render_the_custom_role_permission_matrix(): void
    {
        [$admin] = $this->administrator();

        $this->actingAs($admin)->get(route('workforce.roles.index'))
            ->assertOk()
            ->assertSee('Permission matrix')
            ->assertSee('Available in the Administrator, Manager system role policy.');
    }

    public function test_administrator_can_render_an_employee_detail_page_with_workforce_controls(): void
    {
        [$admin, $branch] = $this->administrator();
        $employee = WorkforceEmployee::create([
            'company_id' => $admin->company_id,
            'primary_branch_id' => $branch->id,
            'employee_number' => 'EMP-DETAIL-001',
            'first_name' => 'Detail',
            'display_name' => 'Detail Employee',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('workforce.employees.show', $employee))
            ->assertOk()
            ->assertSee('Detail Employee')
            ->assertSee('Assignments')
            ->assertSee('Manager reviews')
            ->assertSee('Recognition');
    }

    /** @return array{User, Branch} */
    private function administrator(): array
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create(['is_primary' => true, 'is_active' => true]);
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Administrator, 'is_active' => true, 'account_status' => 'active']);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $user->id]);

        return [$user, $branch];
    }

    /** @return array<string, mixed> */
    private function employeePayload(): array
    {
        return ['employee_number' => 'UNCHANGED', 'first_name' => 'Name', 'display_name' => 'Name', 'status' => 'active'];
    }
}
