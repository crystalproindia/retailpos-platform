<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Support\Navigation\SaasNavigationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResponsiveNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_center_layout_exposes_one_mobile_drawer_control_and_a_desktop_collapse_control(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-sidebar-overlay', false)
            ->assertSee('data-sidebar-close', false)
            ->assertSee('data-sidebar-open aria-controls="command-center-sidebar" aria-expanded="false"', false)
            ->assertSee('lg:hidden', false)
            ->assertSee('data-sidebar-collapse', false);
    }

    public function test_mobile_drawer_styles_use_translate_and_lock_body_scroll_below_desktop(): void
    {
        $styles = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('@media (max-width: 1023px)', $styles);
        $this->assertStringContainsString('body.sidebar-mobile-open {', $styles);
        $this->assertStringContainsString('overflow: hidden;', $styles);
        $this->assertStringContainsString('body.sidebar-mobile-open [data-sidebar] {', $styles);
        $this->assertStringContainsString('translate: 0;', $styles);
    }

    public function test_mobile_navigation_closes_after_navigation_or_escape(): void
    {
        $scripts = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("sidebar?.querySelectorAll('a')", $scripts);
        $this->assertStringContainsString("event.key === 'Escape'", $scripts);
        $this->assertStringContainsString('closeSidebar({ restoreFocus: false })', $scripts);
    }

    public function test_platform_billing_navigation_uses_parameter_free_named_routes(): void
    {
        $platform = $this->user(['is_platform_admin' => true]);
        $registry = app(SaasNavigationRegistry::class);

        $expected = [
            'Billing Dashboard' => 'saas.billing.index',
            'Subscription Invoices' => 'saas.billing.invoices.index',
            'Payments' => 'saas.billing.payments.index',
            'Refunds' => 'saas.billing.refunds.index',
            'Reconciliation' => 'saas.billing.reconciliation.index',
            'Gateway Settings' => 'saas.billing.gateway.index',
            'Billing Reports' => 'saas.billing.reports',
        ];

        $items = collect($registry->platformBillingItems($platform))->keyBy('label');

        foreach ($expected as $label => $routeName) {
            $item = $items->get($label);

            $this->assertNotNull($item, "{$label} is missing from platform billing navigation.");
            $this->assertSame($routeName, $item['route']);
            $this->assertTrue(Route::has($routeName), "{$routeName} is not registered.");
            $this->assertSame([], Route::getRoutes()->getByName($routeName)->parameterNames(), "{$routeName} must not require route parameters.");
            $this->assertNotEmpty($registry->url($item));
        }
    }

    public function test_platform_billing_navigation_routes_resolve_successfully(): void
    {
        $platform = $this->user(['is_platform_admin' => true]);
        $registry = app(SaasNavigationRegistry::class);

        foreach ($registry->platformBillingItems($platform) as $item) {
            $this->actingAs($platform)->get($registry->url($item))->assertOk();
        }
    }

    public function test_shared_saas_navigation_registry_feeds_sidebar_and_saas_nav(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $subnav = file_get_contents(resource_path('views/command-center/saas/partials/nav.blade.php'));

        $this->assertStringContainsString(SaasNavigationRegistry::class, $layout);
        $this->assertStringContainsString('platformItems($user)', $layout);
        $this->assertStringContainsString(SaasNavigationRegistry::class, $subnav);
        $this->assertStringContainsString('platformItems(auth()->user())', $subnav);
    }

    public function test_platform_billing_navigation_active_state_covers_child_routes(): void
    {
        $platform = $this->user(['is_platform_admin' => true]);
        $registry = app(SaasNavigationRegistry::class);
        $items = collect($registry->platformBillingItems($platform))->keyBy('label');

        $this->app->instance('request', $this->requestFor('saas.billing.show'));
        $this->assertTrue($registry->isActive($items->get('Subscription Invoices')));

        $this->app->instance('request', $this->requestFor('saas.billing.payments.index'));
        $this->assertTrue($registry->isActive($items->get('Payments')));

        $this->app->instance('request', $this->requestFor('saas.billing.gateway.index'));
        $this->assertTrue($registry->isActive($items->get('Gateway Settings')));
    }

    public function test_platform_billing_navigation_is_visible_only_to_platform_administrators(): void
    {
        $platform = $this->user(['is_platform_admin' => true]);
        $tenant = $this->user();

        $this->actingAs($platform)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('SaaS Management')
            ->assertSee('Billing Dashboard')
            ->assertSee('Subscription Invoices')
            ->assertSee('Payments')
            ->assertSee('Refunds')
            ->assertSee('Reconciliation')
            ->assertSee('Gateway Settings')
            ->assertSee('Billing Reports');

        $this->actingAs($tenant)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('SaaS Management')
            ->assertDontSee('Billing Dashboard')
            ->assertDontSee('Subscription Invoices')
            ->assertDontSee('Gateway Settings')
            ->assertDontSee('Billing Reports');
    }

    public function test_tenant_subscription_navigation_keeps_tenant_billing_without_platform_links(): void
    {
        $tenant = $this->user();

        $this->actingAs($tenant)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Subscription')
            ->assertSee('Current Plan &amp; Usage', false)
            ->assertSee(route('account.subscription.billing.index'), false)
            ->assertSee('Billing')
            ->assertDontSee(route('saas.billing.index'), false)
            ->assertDontSee('Billing Dashboard');
    }

    public function test_google_calendar_and_meet_are_absent_from_navigation_registries(): void
    {
        $platform = $this->user(['is_platform_admin' => true]);
        $tenant = $this->user();
        $registry = app(SaasNavigationRegistry::class);

        $navigationText = collect([
            ...$registry->platformItems($platform),
            ...$registry->tenantSubscriptionItems($tenant),
        ])->map(fn (array $item): string => $item['label'].' '.$item['route'])->implode(' ');

        $this->assertStringNotContainsString('Google Calendar', $navigationText);
        $this->assertStringNotContainsString('Google Meet', $navigationText);
        $this->assertStringNotContainsString('google', strtolower($navigationText));
        $this->assertStringNotContainsString('meet', strtolower($navigationText));
    }

    private function user(array $attributes = []): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => UserRole::Administrator,
        ] + $attributes);
    }

    private function requestFor(string $routeName): Request
    {
        $route = Route::getRoutes()->getByName($routeName);
        $request = Request::create($route->uri(), 'GET');
        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
