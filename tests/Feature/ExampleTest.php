<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_active_user_can_login_and_view_dashboard(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'email' => 'admin@example.com',
            'role' => UserRole::Administrator,
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Command Center');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login',
            'user_id' => $user->id,
        ]);
    }

    public function test_role_middleware_protects_settings(): void
    {
        $staff = User::factory()->create([
            'role' => UserRole::Staff,
            'password' => 'password',
        ]);

        $this->actingAs($staff)
            ->get('/settings/general')
            ->assertForbidden();
    }

    public function test_manager_can_update_settings_and_create_audit_log(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create([
            'role' => UserRole::Manager,
        ]);

        $this->actingAs($user)
            ->put('/settings/general', [
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'date_format' => 'd M Y',
            ])
            ->assertRedirect();

        $this->assertSame(
            'INR',
            Setting::query()
                ->where('company_id', $company->id)
                ->where('group', 'general')
                ->where('key', 'currency')
                ->first()
                ?->value['value'],
        );

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'event' => 'settings.updated',
        ]);
    }
}
