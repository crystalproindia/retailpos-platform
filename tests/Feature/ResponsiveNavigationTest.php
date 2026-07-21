<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function user(): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => UserRole::Administrator,
        ]);
    }
}
