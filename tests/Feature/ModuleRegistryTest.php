<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleRegistryTest extends TestCase
{
    public function test_registry_loads_configured_modules(): void
    {
        $registry = new ModuleRegistry;

        $this->assertNotNull($registry->find('dashboard'));
        $this->assertSame('Dashboard', $registry->find('dashboard')->name);
        $this->assertSame('crm.dashboard', $registry->find('crm')->route);
        $this->assertSame([], $registry->find('crm')->routeParameters);
    }

    public function test_sidebar_generation_groups_visible_modules(): void
    {
        $registry = new ModuleRegistry;

        $groups = $registry->grouped(UserRole::Administrator);

        $this->assertTrue($groups->has('Overview'));
        $this->assertTrue($groups->has('Administration'));
        $this->assertSame('Dashboard', $groups->get('Overview')->first()->name);
        $this->assertTrue($registry->sidebar(UserRole::Administrator)->contains('id', 'settings'));
        $website = $registry->sidebar(UserRole::Administrator)->firstWhere('id', 'website-cms');

        $this->assertNotNull($website);
        $this->assertSame('Website', $website->name);
        $this->assertContains('website-pages', collect($website->children)->pluck('id'));
        $this->assertContains('website-navigation', collect($website->children)->pluck('id'));
        $this->assertContains('website-settings', collect($website->children)->pluck('id'));
    }

    public function test_role_filtering_returns_only_allowed_modules(): void
    {
        $registry = new ModuleRegistry;

        $staffModules = $registry->forRole(UserRole::Staff);
        $adminModules = $registry->forRole(UserRole::Administrator);

        $this->assertTrue($staffModules->contains('id', 'dashboard'));
        $this->assertFalse($staffModules->contains('id', 'crm'));
        $this->assertFalse($staffModules->contains('id', 'settings'));
        $this->assertFalse($staffModules->contains('id', 'audit-logs'));
        $this->assertTrue($adminModules->contains('id', 'settings'));
        $this->assertFalse($adminModules->contains('id', 'audit-logs'));
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

    public function test_completed_pos_and_gst_modules_are_visible_to_administrators_and_resolve_to_named_routes(): void
    {
        $registry = new ModuleRegistry;
        $modules = $registry->sidebar(UserRole::Administrator);

        $this->assertTrue($modules->contains('id', 'pos'));
        $this->assertTrue($modules->contains('id', 'gst-compliance'));
        $this->assertContains('pos-registers', collect($modules->firstWhere('id', 'pos')->children)->pluck('id'));
        $this->assertContains('gst-exports', collect($modules->firstWhere('id', 'gst-compliance')->children)->pluck('id'));
        $this->assertContains('purchase-input-gst', collect($modules->firstWhere('id', 'purchases')->children)->pluck('id'));
        $this->assertContains('sales-opportunities', collect($modules->firstWhere('id', 'sales')->children)->pluck('id'));
        $this->assertContains('website-media', collect($modules->firstWhere('id', 'website-cms')->children)->pluck('id'));
        $this->assertFalse($registry->enabled()->contains(fn ($module) => $module->route === 'modules.show'));
        $this->assertFalse($registry->all()->contains(fn ($module) => str_contains($module->route, 'google')));

        $registry->enabled()->each(function ($module): void {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($module->route), "{$module->id} uses a missing route");
            $this->assertNotEmpty($module->url());
        });
    }

    public function test_sales_and_pos_parent_modules_are_active_for_their_child_routes(): void
    {
        $this->app->instance('request', $this->requestFor('pos.sales.index'));
        $registry = new ModuleRegistry;
        $this->assertTrue($registry->find('pos')->isActive());

        $this->app->instance('request', $this->requestFor('sales.opportunities.index'));
        $registry = new ModuleRegistry;
        $this->assertTrue($registry->find('sales')->isActive());
    }

    private function requestFor(string $routeName): Request
    {
        $route = Route::getRoutes()->getByName($routeName);
        $request = Request::create($route->uri(), 'GET');
        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
