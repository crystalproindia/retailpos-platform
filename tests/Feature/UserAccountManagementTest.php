<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_account_is_logged_out_before_a_protected_route_is_served(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create(['is_primary' => true]);
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager, 'is_active' => false, 'account_status' => 'suspended']);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $user->id]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_pending_invitation_account_cannot_complete_normal_login(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['email' => 'pending@example.test', 'role' => UserRole::Staff, 'is_active' => false, 'account_status' => 'pending_invitation', 'password' => 'a-strong-password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'a-strong-password'])
            ->assertSessionHasErrors('email');
    }
}
