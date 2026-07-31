<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkforceEmployee;
use App\Models\WorkforceInvitation;
use App\Services\Workforce\WorkforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_and_cancelled_activation_links_fail_without_activating_the_account(): void
    {
        [$administrator, $branch] = $this->administrator();
        $employee = WorkforceEmployee::create(['company_id' => $administrator->company_id, 'primary_branch_id' => $branch->id, 'employee_number' => 'INV-001', 'first_name' => 'Mira', 'display_name' => 'Mira', 'status' => 'draft']);
        $invitation = app(WorkforceService::class)->invite($administrator, $employee, ['name' => 'Mira', 'email' => 'mira@example.test', 'branch_id' => $branch->id, 'role' => UserRole::Staff->value]);
        $token = 'expired-workforce-token';
        $invitation->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->subMinute()]);

        $this->get(route('workforce.invitation.show', ['token' => $token]))->assertNotFound();
        $this->post(route('workforce.invitation.accept', ['token' => $token]), ['password' => 'a-strong-password', 'password_confirmation' => 'a-strong-password'])
            ->assertSessionHasErrors('invitation');
        $this->assertFalse($invitation->user->refresh()->is_active);

        $invitation->update(['expires_at' => now()->addDay(), 'cancelled_at' => now()]);
        $this->get(route('workforce.invitation.show', ['token' => $token]))->assertNotFound();
    }

    public function test_resending_invitation_revokes_the_previous_pending_record(): void
    {
        [$administrator, $branch] = $this->administrator();
        $employee = WorkforceEmployee::create(['company_id' => $administrator->company_id, 'primary_branch_id' => $branch->id, 'employee_number' => 'INV-002', 'first_name' => 'Devi', 'display_name' => 'Devi', 'status' => 'draft']);
        $service = app(WorkforceService::class);
        $first = $service->invite($administrator, $employee, ['name' => 'Devi', 'email' => 'devi@example.test', 'branch_id' => $branch->id, 'role' => UserRole::Staff->value]);
        $second = $service->invite($administrator, $employee, ['name' => 'Devi', 'email' => 'devi@example.test', 'branch_id' => $branch->id, 'role' => UserRole::Staff->value]);

        $this->assertNotNull($first->refresh()->cancelled_at);
        $this->assertNull($second->refresh()->cancelled_at);
        $this->assertSame(1, WorkforceInvitation::query()->where('employee_id', $employee->id)->whereNull('cancelled_at')->count());
    }

    /** @return array{User, Branch} */
    private function administrator(): array
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create(['is_primary' => true, 'is_active' => true]);
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Administrator, 'account_status' => 'active']);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $user->id]);

        return [$user, $branch];
    }
}
