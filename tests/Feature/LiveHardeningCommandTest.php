<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LiveHardeningCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_administrator_password_can_be_rotated_without_outputting_the_password(): void
    {
        $administrator = $this->user(UserRole::Administrator, 'admin@example.test');
        $newPassword = 'A-new-demo-password';

        $this->artisan('retailpos:admin-password', ['--email' => $administrator->email])
            ->expectsQuestion('New password (minimum 12 characters)', $newPassword)
            ->expectsQuestion('Confirm new password', $newPassword)
            ->expectsOutput('Administrator password updated successfully.')
            ->doesntExpectOutput($newPassword)
            ->assertExitCode(0);

        $this->assertTrue(Hash::check($newPassword, $administrator->fresh()->password));
    }

    public function test_password_rotation_refuses_a_non_administrator_account(): void
    {
        $staff = $this->user(UserRole::Staff, 'staff@example.test');

        $this->artisan('retailpos:admin-password', ['--email' => $staff->email])
            ->expectsOutput('No administrator found for that email.')
            ->assertExitCode(1);
    }

    public function test_live_check_reports_read_only_readiness_information(): void
    {
        $usersBefore = User::count();

        $this->artisan('retailpos:live-check')
            ->expectsOutputToContain('RetailPOS live readiness check')
            ->expectsOutputToContain('Database connection')
            ->expectsOutputToContain('Offline POS routes')
            ->expectsOutputToContain('Summary:')
            ->assertExitCode(0);

        $this->assertSame($usersBefore, User::count());
    }

    private function user(UserRole $role, string $email): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'email' => $email,
            'role' => $role,
        ]);
    }
}
