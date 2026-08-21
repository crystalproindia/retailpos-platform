<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Services\Navigation\GlobalMenuSearchService;
use App\Services\Navigation\NavigationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_visible_modules_pins_and_order_for_their_own_workspace(): void
    {
        $user = $this->user(UserRole::Administrator);

        $this->actingAs($user)
            ->put(route('navigation.preferences.update'), [
                'visible_module_ids' => ['dashboard', 'customers', 'pos'],
                'pinned_module_ids' => ['pos', 'customers'],
                'module_order' => ['pos', 'customers', 'dashboard'],
            ])
            ->assertRedirect(route('navigation.preferences.edit'));

        $preferences = app(NavigationPreferenceService::class);

        $this->assertSame(['pos', 'customers', 'dashboard'], $preferences->visibleModules($user)->pluck('id')->all());
        $this->assertSame(['pos', 'customers'], $preferences->pinnedModules($user)->pluck('id')->all());
        $this->assertSame([], $preferences->hiddenModules($user)->pluck('id')->intersect(['customers', 'pos'])->all());
        $this->assertDatabaseHas('user_navigation_preferences', [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
        ]);
    }

    public function test_hidden_modules_are_removed_from_sidebar_but_remain_recoverable_in_command_palette(): void
    {
        $user = $this->user(UserRole::Administrator);

        $this->actingAs($user)->put(route('navigation.preferences.update'), [
            'visible_module_ids' => ['dashboard'],
            'pinned_module_ids' => [],
            'module_order' => ['dashboard'],
        ])->assertRedirect();

        $this->assertFalse($preferences = app(NavigationPreferenceService::class)
            ->sidebarForUser($user)
            ->contains('id', 'customers'));

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Hidden modules')
            ->assertSee('Hidden from sidebar');

        $entries = app(GlobalMenuSearchService::class)->entriesFor($user);
        $customers = $entries->firstWhere('route', 'customers.index');

        $this->assertNotNull($customers);
        $this->assertTrue($customers['hidden']);
    }

    public function test_preferences_are_isolated_per_user_and_company(): void
    {
        $company = Company::factory()->create();
        $first = User::factory()->for($company)->create(['role' => UserRole::Administrator]);
        $second = User::factory()->for($company)->create(['role' => UserRole::Administrator]);
        $otherCompanyUser = $this->user(UserRole::Administrator);

        $this->actingAs($first)->put(route('navigation.preferences.update'), [
            'visible_module_ids' => ['dashboard'],
            'pinned_module_ids' => ['dashboard'],
            'module_order' => ['dashboard'],
        ])->assertRedirect();

        $preferences = app(NavigationPreferenceService::class);

        $this->assertContains('customers', $preferences->visibleModules($second)->pluck('id')->all());
        $this->assertContains('customers', $preferences->visibleModules($otherCompanyUser)->pluck('id')->all());
        $this->assertSame(['dashboard'], $preferences->visibleModules($first)->pluck('id')->all());
    }

    public function test_presets_and_manipulated_module_ids_never_grant_navigation_access(): void
    {
        $staff = $this->user(UserRole::Staff);

        $this->actingAs($staff)->put(route('navigation.preferences.update'), [
            'visible_module_ids' => ['dashboard', 'purchases', 'unknown-module'],
            'pinned_module_ids' => ['purchases', 'unknown-module'],
            'module_order' => ['purchases', 'dashboard'],
        ])->assertRedirect();

        $preferences = app(NavigationPreferenceService::class);
        $visible = $preferences->visibleModules($staff)->pluck('id')->all();

        $this->assertSame(['dashboard'], $visible);
        $this->assertSame([], $preferences->pinnedModules($staff)->pluck('id')->all());

        $this->actingAs($staff)->put(route('navigation.preferences.update'), [
            'selected_preset' => 'full_admin',
            'apply_preset' => true,
        ])->assertRedirect();

        $this->assertNotContains('purchases', $preferences->visibleModules($staff)->pluck('id')->all());
        $this->assertFalse(app(GlobalMenuSearchService::class)->entriesFor($staff)->contains('route', 'purchases.orders.index'));
    }

    public function test_reset_restores_authorized_defaults_and_dashboard_exposes_smart_navigation_surfaces(): void
    {
        config(['store_setup.enabled' => false]);
        $user = $this->user(UserRole::Administrator);

        $this->actingAs($user)->put(route('navigation.preferences.update'), [
            'visible_module_ids' => ['dashboard'],
            'pinned_module_ids' => ['dashboard'],
            'module_order' => ['dashboard'],
        ])->assertRedirect();

        $this->actingAs($user)->post(route('navigation.preferences.reset'))
            ->assertRedirect(route('navigation.preferences.edit'));

        $this->assertDatabaseHas('user_navigation_preferences', [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'selected_preset' => 'full_admin',
        ]);

        $preferences = app(NavigationPreferenceService::class);
        $this->assertContains('customers', $preferences->visibleModules($user)->pluck('id')->all());

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Quick Actions')
            ->assertSee('My Shortcuts')
            ->assertSee('All Modules')
            ->assertSee(route('navigation.preferences.edit'), false);

        $this->actingAs($user)->get(route('navigation.preferences.edit'))
            ->assertOk()
            ->assertSee('Reset navigation to defaults')
            ->assertSee('data-confirm-dialog', false)
            ->assertSee('data-confirm-submit-label="Reset navigation"', false);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->for(Company::factory())->create(['role' => $role]);
    }
}
