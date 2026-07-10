<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Support\Modules\ModuleRegistry;
use Tests\TestCase;

class ModuleRegistryTest extends TestCase
{
    public function test_registry_loads_configured_modules(): void
    {
        $registry = new ModuleRegistry;

        $this->assertNotNull($registry->find('dashboard'));
        $this->assertSame('Dashboard', $registry->find('dashboard')->name);
        $this->assertSame('modules.show', $registry->find('crm')->route);
        $this->assertSame(['module' => 'crm'], $registry->find('crm')->routeParameters);
    }

    public function test_sidebar_generation_groups_visible_modules(): void
    {
        $registry = new ModuleRegistry;

        $groups = $registry->grouped(UserRole::Administrator);

        $this->assertTrue($groups->has('Overview'));
        $this->assertTrue($groups->has('Administration'));
        $this->assertSame('Dashboard', $groups->get('Overview')->first()->name);
        $this->assertTrue($registry->sidebar(UserRole::Administrator)->contains('id', 'settings'));
        $this->assertFalse($registry->sidebar(UserRole::Administrator)->contains('id', 'website-cms'));
    }

    public function test_role_filtering_returns_only_allowed_modules(): void
    {
        $registry = new ModuleRegistry;

        $staffModules = $registry->forRole(UserRole::Staff);
        $adminModules = $registry->forRole(UserRole::Administrator);

        $this->assertTrue($staffModules->contains('id', 'dashboard'));
        $this->assertFalse($staffModules->contains('id', 'settings'));
        $this->assertFalse($staffModules->contains('id', 'audit-logs'));
        $this->assertTrue($adminModules->contains('id', 'settings'));
        $this->assertTrue($adminModules->contains('id', 'audit-logs'));
    }

    public function test_disabled_modules_are_excluded_from_enabled_and_sidebar_results(): void
    {
        $modules = config('modules.modules');
        $modules['crm']['enabled'] = false;

        config(['modules.modules' => $modules]);

        $registry = new ModuleRegistry;

        $this->assertNotNull($registry->find('crm'));
        $this->assertFalse($registry->find('crm')->enabled);
        $this->assertFalse($registry->enabled()->contains('id', 'crm'));
        $this->assertFalse($registry->sidebar(UserRole::Administrator)->contains('id', 'crm'));
    }
}
