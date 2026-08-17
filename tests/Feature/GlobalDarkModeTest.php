<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalDarkModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_center_and_pos_layouts_use_the_shared_theme_scope(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('command-center-theme', false)
            ->assertSee('dark:bg-slate-950', false);

        $this->actingAs($administrator)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('command-center-theme pos-shell', false)
            ->assertSee('dark:text-slate-100', false);
    }

    public function test_guest_signup_and_portal_layouts_provide_dark_surfaces(): void
    {
        $guestLayout = file_get_contents(resource_path('views/layouts/guest.blade.php'));
        $signupLayout = file_get_contents(resource_path('views/layouts/public-signup.blade.php'));
        $portalLayout = file_get_contents(resource_path('views/layouts/portal.blade.php'));

        $this->assertStringContainsString('command-center-theme min-h-screen bg-slate-950', $guestLayout);
        $this->assertStringContainsString('dark:bg-slate-900', $guestLayout);
        $this->assertStringContainsString('command-center-theme min-h-screen bg-slate-50', $signupLayout);
        $this->assertStringContainsString('dark:bg-slate-950', $signupLayout);
        $this->assertStringContainsString('command-center-theme min-h-screen bg-slate-50', $portalLayout);
        $this->assertStringContainsString('dark:bg-slate-900/95', $portalLayout);
    }

    public function test_shared_dark_mode_primitives_cover_legacy_and_custom_surfaces(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.dark input:disabled', $css);
        $this->assertStringContainsString(".dark .command-center-theme .bg-white:not([class*='dark:bg-'])", $css);
        $this->assertStringContainsString(".dark .command-center-theme .text-slate-500:not([class*='dark:text-'])", $css);
        $this->assertStringContainsString(".dark .command-center-theme [class~='bg-white/75']", $css);
        $this->assertStringContainsString('.dark .command-center-theme .bg-gray-200', $css);
        $this->assertStringContainsString('.dark .command-center-theme :is(.bg-indigo-50, .bg-indigo-100, .bg-violet-50, .bg-violet-100)', $css);
        $this->assertStringContainsString('.dark .cms-light-workspace .cms-panel', $css);
        $this->assertStringContainsString('.dark .pos-payment-sheet', $css);
        $this->assertStringContainsString('.dark .pos-feedback.is-error', $css);
        $this->assertStringContainsString('.theme-preserve-light', $css);
    }

    public function test_gst_settings_uses_semantic_dark_warning_and_form_surfaces(): void
    {
        $this->actingAs($this->administrator())
            ->get(route('compliance.gst.settings.index'))
            ->assertOk()
            ->assertSee('Accountant review required')
            ->assertSee('dark:border-amber-800/70', false)
            ->assertSee('dark:bg-amber-950/30', false)
            ->assertSee('dark:border-slate-800', false)
            ->assertSee('dark:bg-slate-900', false)
            ->assertSee('dark:text-slate-100', false);
    }

    public function test_printable_pos_receipt_preserves_its_light_document_canvas(): void
    {
        $view = file_get_contents(resource_path('views/command-center/pos/receipt.blade.php'));

        $this->assertStringContainsString('theme-preserve-light pos-receipt', $view);
    }

    private function administrator(): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);

        return User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => UserRole::Administrator,
        ]);
    }
}
