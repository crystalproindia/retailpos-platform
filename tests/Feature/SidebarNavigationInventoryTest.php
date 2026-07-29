<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Services\Navigation\GlobalMenuSearchService;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sidebar_inventory_is_complete_and_ordered(): void
    {
        $inventory = $this->inventoryFor($this->user(UserRole::Administrator));

        $this->assertSame([
            'dashboard' => [],
            'crm' => ['crm-dashboard', 'contacts', 'leads', 'demo-requests', 'crm-quotations', 'crm-customers', 'crm-proformas', 'crm-onboarding', 'crm-companies', 'crm-pipeline', 'crm-activities', 'crm-follow-ups'],
            'sales' => ['sales-invoices', 'sales-opportunities'],
            'pos' => ['pos-dashboard', 'pos-billing', 'pos-held', 'pos-offline', 'pos-sales', 'pos-registers'],
            'customers' => ['customer-dashboard', 'customer-records', 'customer-groups', 'customer-loyalty', 'customer-birthdays', 'customer-insights', 'customer-inactive', 'customer-lost', 'customer-returns', 'customer-wallet', 'customer-settings'],
            'orders' => [],
            'promotions' => ['promotion-dashboard', 'promotion-campaigns', 'promotion-rules', 'promotion-buy-x-get-y', 'promotion-coupons', 'promotion-combos', 'promotion-product-offers', 'promotion-category-offers', 'promotion-brand-offers', 'promotion-channel-offers', 'promotion-branch-offers', 'promotion-simulator', 'promotion-usage', 'promotion-settings'],
            'gst-compliance' => ['gst-settings', 'gst-notes', 'gst-reports', 'gst-exports', 'gst-periods', 'gst-series', 'gst-eway', 'gst-guide'],
            'inventory' => ['inventory-dashboard', 'inventory-decision-dashboard', 'products', 'inventory-categories', 'inventory-brands', 'inventory-units', 'inventory-tax-rates', 'inventory-variants', 'inventory-barcodes', 'inventory-barcode-labels', 'inventory-warehouses', 'inventory-stock-locations', 'inventory-stock-ledger', 'inventory-stock-adjustments', 'inventory-opening-stock', 'inventory-low-stock', 'inventory-reorder-suggestions', 'inventory-sales-channels', 'inventory-channel-mapping', 'inventory-settings', 'inventory-transfers'],
            'purchases' => ['purchase-dashboard', 'supplier-dashboard', 'suppliers', 'supplier-contacts', 'supplier-products', 'supplier-ratings', 'purchase-requests', 'purchase-orders', 'goods-receipts', 'purchase-returns', 'pending-approvals', 'reorder-to-purchase', 'purchase-settings', 'purchase-invoices', 'supplier-payments', 'purchase-reports', 'purchase-input-gst'],
            'projects' => [],
            'support' => [],
            'finance' => [],
            'expenses' => [],
            'employees' => [],
            'hr' => [],
            'payroll' => [],
            'marketing' => [],
            'whatsapp' => [],
            'cms' => ['cms-control-center', 'cms-branding', 'cms-theme', 'cms-pages', 'cms-content-library', 'cms-seo-center', 'cms-seo-pages', 'cms-landing-pages', 'cms-articles', 'cms-redirects'],
            'blog' => [],
            'website-cms' => ['website-pages', 'website-navigation', 'website-settings', 'website-dashboard', 'website-case-studies', 'website-media', 'website-import'],
            'seo' => [],
            'reports' => [],
            'analytics' => [],
            'ai-assistant' => [],
            'company' => [],
            'branches' => [],
            'users' => [],
            'roles' => [],
            'settings' => ['invoice-designs', 'invoice-reminders'],
            'integrations' => [],
            'operations' => ['operations-health', 'operations-queue', 'operations-failed-jobs', 'operations-schedule', 'operations-notification-deliveries', 'operations-webhooks', 'operations-event-logs', 'operations-application'],
            'notifications' => ['notification-inbox', 'notification-preferences', 'notification-event-log', 'notification-webhooks', 'notification-delivery-log'],
            'audit-logs' => [],
        ], $inventory);
    }

    public function test_manager_sales_and_staff_receive_their_authorised_navigation_inventory(): void
    {
        $this->assertSame([
            'dashboard', 'crm', 'sales', 'pos', 'customers', 'orders', 'promotions', 'gst-compliance', 'inventory', 'purchases', 'projects', 'support', 'finance', 'expenses', 'employees', 'hr', 'marketing', 'whatsapp', 'cms', 'blog', 'website-cms', 'seo', 'reports', 'analytics', 'ai-assistant', 'company', 'branches', 'settings', 'operations', 'notifications',
        ], array_keys($this->inventoryFor($this->user(UserRole::Manager))));

        $this->assertSame([
            'dashboard', 'crm', 'sales', 'pos', 'customers', 'orders', 'promotions', 'inventory', 'support', 'notifications',
        ], array_keys($this->inventoryFor($this->user(UserRole::Sales))));

        $this->assertSame(['dashboard', 'orders'], array_keys($this->inventoryFor($this->user(UserRole::Staff))));
    }

    public function test_restored_reports_ai_users_and_invoice_settings_follow_role_boundaries(): void
    {
        $administrator = $this->inventoryFor($this->user(UserRole::Administrator));
        $manager = $this->inventoryFor($this->user(UserRole::Manager));
        $sales = $this->inventoryFor($this->user(UserRole::Sales));
        $staff = $this->inventoryFor($this->user(UserRole::Staff));

        foreach (['reports', 'ai-assistant', 'users'] as $module) {
            $this->assertArrayHasKey($module, $administrator);
        }
        $this->assertSame(['invoice-designs', 'invoice-reminders'], $administrator['settings']);
        $this->assertArrayHasKey('reports', $manager);
        $this->assertArrayHasKey('ai-assistant', $manager);
        $this->assertArrayNotHasKey('users', $manager);
        foreach (['reports', 'ai-assistant', 'users', 'settings'] as $module) {
            $this->assertArrayNotHasKey($module, $sales);
            $this->assertArrayNotHasKey($module, $staff);
        }
    }

    public function test_authorised_child_keeps_an_unauthorised_parent_visible_as_a_non_clickable_group(): void
    {
        $modules = config('modules.modules');
        $modules['restricted-parent'] = $this->module('Restricted parent', ['manager']);
        $modules['authorised-child'] = $this->module('Authorised child', ['administrator'], 'restricted-parent');
        config(['modules.modules' => $modules]);

        $parent = (new ModuleRegistry)->sidebarForUser($this->user(UserRole::Administrator))->firstWhere('id', 'restricted-parent');

        $this->assertNotNull($parent);
        $this->assertFalse($parent->navigable);
        $this->assertSame(['authorised-child'], collect($parent->children)->pluck('id')->all());
    }

    public function test_parent_is_hidden_when_neither_it_nor_its_children_are_authorised(): void
    {
        $modules = config('modules.modules');
        $modules['hidden-parent'] = $this->module('Hidden parent', ['manager']);
        $modules['hidden-child'] = $this->module('Hidden child', ['sales'], 'hidden-parent');
        config(['modules.modules' => $modules]);

        $this->assertFalse((new ModuleRegistry)->sidebarForUser($this->user(UserRole::Administrator))->contains('id', 'hidden-parent'));
    }

    public function test_alias_free_and_search_excluded_modules_remain_in_the_sidebar_without_leaking_to_search(): void
    {
        $modules = config('modules.modules');
        $modules['alias-free-module'] = $this->module('Alias-free module', ['administrator'], searchable: true);
        $modules['search-excluded-module'] = $this->module('Search-excluded module', ['administrator'], searchable: false);
        config(['modules.modules' => $modules]);

        $administrator = $this->user(UserRole::Administrator);
        $sidebar = (new ModuleRegistry)->sidebarForUser($administrator);
        $entries = app(GlobalMenuSearchService::class)->entriesFor($administrator);

        $this->assertTrue($sidebar->contains('id', 'alias-free-module'));
        $this->assertTrue($sidebar->contains('id', 'search-excluded-module'));
        $this->assertTrue($entries->contains('navigation_key', 'module:alias-free-module'));
        $this->assertFalse($entries->contains('navigation_key', 'module:search-excluded-module'));
    }

    public function test_global_search_preserves_distinct_navigation_items_that_share_a_route(): void
    {
        $entries = app(GlobalMenuSearchService::class)->entriesFor($this->user(UserRole::Administrator));

        $this->assertSame(['module:crm', 'module:crm-dashboard'], $entries->where('route', 'crm.dashboard')->pluck('navigation_key')->all());
        $this->assertCount($entries->count(), $entries->pluck('navigation_key')->unique());
    }

    public function test_disabled_modules_remain_hidden_from_sidebar_and_global_search(): void
    {
        $modules = config('modules.modules');
        $modules['reports']['enabled'] = false;
        config(['modules.modules' => $modules]);

        $administrator = $this->user(UserRole::Administrator);
        $sidebar = (new ModuleRegistry)->sidebarForUser($administrator);

        $this->assertFalse($sidebar->contains('id', 'reports'));
        $this->assertFalse(app(GlobalMenuSearchService::class)->entriesFor($administrator)->contains('navigation_key', 'module:reports'));
    }

    public function test_shared_desktop_and_mobile_layout_uses_the_same_authorised_registry(): void
    {
        config(['store_setup.enabled' => false]);
        $response = $this->actingAs($this->user(UserRole::Administrator))->get('/dashboard');

        $response->assertOk()
            ->assertSee('Reports')
            ->assertSee('AI Assistant')
            ->assertSee('Users')
            ->assertSee('Invoice Designs')
            ->assertSee('Invoice Reminders')
            ->assertSee('data-sidebar', false)
            ->assertSee('data-sidebar-overlay', false);
    }

    /** @return array<string, array<int, string>> */
    private function inventoryFor(User $user): array
    {
        return (new ModuleRegistry)->sidebarForUser($user)
            ->mapWithKeys(fn ($module): array => [$module->id => collect($module->children)->pluck('id')->all()])
            ->all();
    }

    /** @return array<string, mixed> */
    private function module(string $name, array $roles, ?string $parentId = null, bool $searchable = true): array
    {
        return [
            'name' => $name,
            'description' => 'Navigation regression fixture.',
            'icon' => 'dashboard',
            'route' => 'dashboard',
            'route_params' => [],
            'sort_order' => 99_999,
            'category' => 'Testing',
            'enabled' => true,
            'visible_in_sidebar' => true,
            'roles' => $roles,
            'badge' => null,
            'license_key' => null,
            'parent_id' => $parentId,
            'searchable' => $searchable,
        ];
    }

    private function user(UserRole $role): User
    {
        return User::factory()->for(Company::factory())->create(['role' => $role]);
    }
}
