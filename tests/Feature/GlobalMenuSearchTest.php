<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Services\Navigation\GlobalMenuSearchService;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalMenuSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_manager_receive_permitted_invoice_design_navigation(): void
    {
        foreach ([UserRole::Administrator, UserRole::Manager] as $role) {
            $entries = app(GlobalMenuSearchService::class)->entriesFor($this->user($role));
            $invoiceDesigns = $entries->firstWhere('route', 'sales.invoices.templates.index');

            $this->assertNotNull($invoiceDesigns);
            $this->assertSame('Invoice Designs', $invoiceDesigns['label']);
            $this->assertContains('bill design', $invoiceDesigns['aliases']);
        }
    }

    public function test_sales_and_staff_do_not_receive_management_only_invoice_design_navigation(): void
    {
        foreach ([UserRole::Sales, UserRole::Staff] as $role) {
            $entries = app(GlobalMenuSearchService::class)->entriesFor($this->user($role));

            $this->assertFalse($entries->contains('route', 'sales.invoices.templates.index'));
        }
    }

    public function test_index_uses_visible_enabled_modules_and_deduplicates_routes(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $entries = app(GlobalMenuSearchService::class)->entriesFor($administrator);

        $this->assertSame(1, $entries->where('route', 'crm.dashboard')->count());
        $this->assertFalse($entries->contains('label', 'Asha Retail'));
        $this->assertSame(['label', 'route', 'url', 'icon', 'breadcrumb', 'group', 'aliases'], array_keys($entries->first()));

        $modules = config('modules.modules');
        $modules['invoice-designs']['enabled'] = false;
        config(['modules.modules' => $modules]);
        $service = new GlobalMenuSearchService(new ModuleRegistry, app(\App\Support\Navigation\SaasNavigationRegistry::class));

        $this->assertFalse($service->entriesFor($administrator)->contains('route', 'sales.invoices.templates.index'));
    }

    public function test_aliases_cover_common_menu_language_without_a_second_navigation_list(): void
    {
        $aliases = app(GlobalMenuSearchService::class)->aliases();

        $this->assertContains('invoice', $aliases['bill']);
        $this->assertContains('inventory', $aliases['stock']);
        $this->assertContains('supplier', $aliases['vendor']);
        $this->assertContains('compliance', $aliases['gst']);
        $this->assertContains('invoice designs', $aliases['bill design']);
    }

    public function test_search_control_and_mobile_trigger_render_from_the_shared_layout(): void
    {
        $administrator = $this->user(UserRole::Administrator);

        $this->actingAs($administrator)->get('/dashboard')
            ->assertOk()
            ->assertSee('data-global-menu-search-open', false)
            ->assertSee('data-global-menu-dialog', false)
            ->assertSee('Search menus, modules or settings...', false)
            ->assertSee('Invoice Designs');
    }

    public function test_command_palette_script_supports_keyboard_search_recent_navigation_and_safe_client_storage(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("event.key.toLowerCase() === 'k'", $script);
        $this->assertStringContainsString("event.key === 'ArrowDown'", $script);
        $this->assertStringContainsString("event.key === 'ArrowUp'", $script);
        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString('localStorage.getItem(recentKey)', $script);
        $this->assertStringContainsString('route: item.dataset.route', $script);
        $this->assertStringContainsString('sources.get(entry.route)', $script);
        $this->assertStringContainsString('storeRecentMenus(recent.map', $script);
        $this->assertStringContainsString('clearRecentMenus()', $script);
        $this->assertStringContainsString('editDistance', $script);
        $this->assertStringNotContainsString('fetch(', substr($script, strpos($script, 'const menuDialog'), 9000));
    }

    private function user(UserRole $role): User
    {
        $company = Company::factory()->create();

        return User::factory()->for($company)->create(['role' => $role]);
    }
}
