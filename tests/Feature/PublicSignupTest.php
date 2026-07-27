<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Contracts\Saas\MobileOtpSender;
use App\Mail\PublicSignupVerificationCode;
use App\Models\Branch;
use App\Models\Company;
use App\Models\SaasIndustry;
use App\Models\SaasPlan;
use App\Models\SaasPublicSignupSession;
use App\Models\User;
use App\Services\Saas\PublicFree365SignupService;
use App\Services\Saas\Free365OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('saas.public_signup.enabled', true);
        config()->set('saas.public_signup.email_otp_enabled', true);
        config()->set('saas.public_signup.mobile_otp_enabled', false);
        $this->freePlan();
    }

    public function test_signup_page_is_safe_when_disabled_and_does_not_expose_plan_ids(): void
    {
        config()->set('saas.public_signup.enabled', false);
        $this->get(route('saas.public-signup.show'))->assertOk()->assertSee('currently unavailable')->assertDontSee('saas_plan_id');
    }

    public function test_enabled_industries_render_and_disabled_ones_do_not(): void
    {
        app(\App\Services\Saas\IndustryRegistry::class)->enabled();
        SaasIndustry::where('key', 'jewellery')->update(['is_enabled' => false]);
        $this->get(route('saas.public-signup.show'))->assertOk()->assertSee('Fashion &amp; Apparel', false)->assertDontSee('Jewellery');
    }

    public function test_email_otp_starts_a_short_lived_hashed_pending_signup(): void
    {
        Mail::fake();
        $response = $this->post(route('saas.public-signup.begin'), ['industry' => 'general_retail', 'verification_method' => 'email', 'email' => 'owner@example.test']);
        $response->assertRedirect(route('saas.public-signup.show'));
        $record = SaasPublicSignupSession::firstOrFail();
        $this->assertSame('owner@example.test', $record->email);
        $this->assertNotNull($record->verification_code_hash);
        $this->assertNull($record->verified_at);
        $this->assertGreaterThan(now(), $record->expires_at);
        Mail::assertSent(PublicSignupVerificationCode::class);
    }

    public function test_verified_signup_provisions_one_free365_tenant_and_is_idempotent(): void
    {
        Mail::fake();
        $started = app(PublicFree365SignupService::class)->begin('general_retail', 'email', 'owner@example.test', '127.0.0.1', 'test');
        $record = $started['session'];
        $record->update(['verification_code_hash' => Hash::make('123456')]);
        app(PublicFree365SignupService::class)->verify($started['token'], '123456');
        $payload = ['name' => 'Asha Owner', 'password' => 'password-with-enough-length', 'company_name' => null, 'terms' => true];
        app(PublicFree365SignupService::class)->complete($started['token'], $payload);
        app(PublicFree365SignupService::class)->complete($started['token'], $payload);

        $record->refresh();
        $company = Company::findOrFail($record->onboarding->company_id);
        $owner = $company->users()->firstOrFail();
        $this->assertSame('Your Store Name', $company->name);
        $this->assertSame('verified', $owner->verification_status);
        $this->assertTrue(Hash::check('password-with-enough-length', $owner->password));
        $this->assertDatabaseCount('saas_subscriptions', 1);
        $this->assertDatabaseCount('branches', 1);
        $this->assertSame('public', $record->onboarding->signup_source);
    }

    public function test_unverified_or_duplicate_contact_cannot_provision_a_public_free_account(): void
    {
        Mail::fake();
        $started = app(PublicFree365SignupService::class)->begin('general_retail', 'email', 'owner@example.test', '127.0.0.1', null);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PublicFree365SignupService::class)->complete($started['token'], ['name' => 'Asha', 'password' => 'password-with-enough-length', 'terms' => true]);
    }

    public function test_mobile_option_is_hidden_until_a_provider_is_configured(): void
    {
        $this->get(route('saas.public-signup.show'))->assertOk()->assertDontSee('Mobile verification');
        config()->set('saas.public_signup.mobile_otp_enabled', true);
        config()->set('saas.public_signup.mobile_otp_provider', 'test-provider');
        $this->app->instance(MobileOtpSender::class, new class implements MobileOtpSender {
            public function isConfigured(): bool { return true; }
            public function send(string $mobile, string $code): void {}
        });
        $this->get(route('saas.public-signup.show'))->assertOk()->assertSee('Mobile verification');
    }

    public function test_mobile_login_uses_normalized_mobile_identifier(): void
    {
        [$company, $user] = $this->tenant();
        $user->update(['mobile' => '+919876543210', 'password' => Hash::make('password-with-enough-length')]);
        $this->post(route('login'), ['email' => '9876543210', 'password' => 'password-with-enough-length'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_free365_onboarding_checklist_is_tenant_scoped_and_store_name_reminder_clears(): void
    {
        [$company, $user] = $this->tenant();
        $company->update(['name' => 'Your Store Name']);
        app(\App\Services\Saas\SubscriptionService::class)->create($company, $this->freePlan(), $user);
        $checklist = app(Free365OnboardingService::class)->checklist($user);
        $this->assertNotNull($checklist);
        $this->assertFalse(collect($checklist['items'])->firstWhere('key', 'store_name')['complete']);
        $company->update(['name' => 'Asha Mart']);
        $updated = app(Free365OnboardingService::class)->checklist($user);
        $this->assertTrue(collect($updated['items'])->firstWhere('key', 'store_name')['complete']);
        app(Free365OnboardingService::class)->dismiss($user);
        $this->assertTrue(app(Free365OnboardingService::class)->checklist($user)['dismissed']);
    }

    private function freePlan(): SaasPlan
    {
        $plan = SaasPlan::query()->firstOrCreate(['code' => config('saas.free365_plan_code')], ['name' => 'Free 365', 'status' => 'active', 'billing_interval' => 'yearly', 'currency' => 'INR']);
        foreach (['dashboard.basic', 'pos.billing', 'sales.invoices', 'inventory.basic', 'customers.basic'] as $feature) $plan->features()->updateOrCreate(['feature_key' => $feature], ['is_enabled' => true]);
        foreach (['users' => 1, 'branches' => 1, 'monthly_invoices' => 25] as $key => $value) $plan->limits()->updateOrCreate(['limit_key' => $key], ['limit_value' => $value]);
        return $plan;
    }

    /** @return array{Company, User} */
    private function tenant(): array
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Administrator]);
        return [$company, $user];
    }
}
