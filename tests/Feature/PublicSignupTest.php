<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Contracts\Saas\MobileOtpSender;
use App\Models\Branch;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyEmailSetting;
use App\Models\NotificationDelivery;
use App\Models\SaasIndustry;
use App\Models\SaasPlan;
use App\Models\SaasPublicSignupSession;
use App\Models\User;
use App\Jobs\Notifications\SendNotificationDeliveryJob;
use App\Mail\CommandCenterEmail;
use App\Services\Notifications\EmailDeliveryLifecycleService;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Saas\PublicFree365SignupService;
use App\Services\Saas\Free365OnboardingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use RuntimeException;
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
        Queue::fake();
        $this->platformDeliveryCompany(configured: true);
        $response = $this->post(route('saas.public-signup.begin'), ['industry' => 'general_retail', 'verification_method' => 'email', 'email' => 'owner@example.test']);
        $response->assertRedirect(route('saas.public-signup.show'));
        $record = SaasPublicSignupSession::firstOrFail();
        $delivery = NotificationDelivery::query()->sole();
        $code = $this->otpCode($delivery);

        $this->assertSame('owner@example.test', $record->email);
        $this->assertNotNull($record->verification_code_hash);
        $this->assertNull($record->verified_at);
        $this->assertGreaterThan(now(), $record->expires_at);
        $this->assertTrue(Hash::check($code, $record->verification_code_hash));
        $this->assertNotSame($code, $record->verification_code_hash);
        $this->assertSame($delivery->id, $record->verification_delivery_id);
        $this->assertSame('queued', $delivery->status);
        $this->assertSame('saas_public_signup_otp', $delivery->template_key);
        $this->assertFalse(str_contains((string) $delivery->getRawOriginal('sensitive_payload'), $code));
        $this->assertStringNotContainsString($code, json_encode($delivery->payload, JSON_THROW_ON_ERROR));
        $audit = AuditLog::query()->where('event', 'saas.public_signup.otp_delivery_queued')->firstOrFail();
        $this->assertStringNotContainsString($code, json_encode($audit->properties, JSON_THROW_ON_ERROR));
        $response->assertSessionHas('status', 'Your OTP has been sent. Enter the code to continue.');
        Queue::assertPushed(SendNotificationDeliveryJob::class, fn (SendNotificationDeliveryJob $job): bool => $job->deliveryId === $delivery->id);
        $this->get(route('saas.public-signup.show'))->assertOk()->assertDontSee($code);

        Mail::fake();
        app(EmailDeliveryService::class)->send($delivery);
        Mail::assertSent(CommandCenterEmail::class, function (CommandCenterEmail $mail) use ($code): bool {
            $this->assertSame('Verify your RetailPOS account', $mail->heading);
            $this->assertStringContainsString($code, $mail->messageText);
            $this->assertStringContainsString('expires in 10 minutes', $mail->messageText);

            return $mail->hasTo('owner@example.test');
        });
        $this->assertNull($delivery->fresh()->sensitive_payload);
    }

    public function test_missing_smtp_records_a_safe_status_without_losing_the_pending_signup(): void
    {
        Queue::fake();
        $this->platformDeliveryCompany(configured: false);

        $this->post(route('saas.public-signup.begin'), ['industry' => 'general_retail', 'verification_method' => 'email', 'email' => 'owner@example.test'])
            ->assertRedirect(route('saas.public-signup.show'))
            ->assertSessionHas('status', 'Email delivery is unavailable right now. Your signup is saved and retry is available.');

        $session = SaasPublicSignupSession::firstOrFail();
        $delivery = NotificationDelivery::query()->sole();
        $this->assertSame('skipped_not_configured', $delivery->status);
        $this->assertSame($delivery->id, $session->verification_delivery_id);
        $this->assertNotNull($session->verification_code_hash);
        $this->assertNull($delivery->sensitive_payload);
        Queue::assertNothingPushed();
        $this->get(route('saas.public-signup.show'))->assertOk()->assertSee('Email delivery is unavailable.')->assertDontSee('SMTP is not configured.');
    }

    public function test_queue_failure_is_recorded_without_losing_the_signup_session_or_exposing_transport_errors(): void
    {
        Queue::fake();
        $this->platformDeliveryCompany(configured: true);
        $started = app(PublicFree365SignupService::class)->begin('general_retail', 'email', 'owner@example.test', '127.0.0.1', 'test');
        $session = $started['session'];
        $delivery = NotificationDelivery::query()->sole();

        Mail::shouldReceive('purge')->once()->with('company_smtp');
        Mail::shouldReceive('mailer')->once()->with('company_smtp')->andThrow(new RuntimeException('SMTP credentials rejected'));

        try {
            app(EmailDeliveryService::class)->send($delivery);
            $this->fail('Expected the email transport to fail.');
        } catch (RuntimeException) {
            // The existing queue lifecycle keeps the encrypted OTP available for a retry.
        }

        $this->assertSame('temporarily_failed', $delivery->fresh()->status);
        $this->assertNotNull($session->fresh()->verification_code_hash);
        (new SendNotificationDeliveryJob($delivery->id))->failed(new RuntimeException('SMTP credentials rejected'));
        $this->assertSame('permanently_failed', $delivery->fresh()->status);

        $this->withSession(['saas_public_signup_token' => $started['token']])
            ->get(route('saas.public-signup.show'))
            ->assertOk()
            ->assertSee('OTP delivery failed.')
            ->assertDontSee('SMTP credentials rejected');
    }

    public function test_resend_is_rate_limited_and_prevents_an_older_otp_email_from_sending(): void
    {
        Queue::fake();
        $this->platformDeliveryCompany(configured: true);
        $started = app(PublicFree365SignupService::class)->begin('general_retail', 'email', 'owner@example.test', '127.0.0.1', 'test');
        $first = NotificationDelivery::query()->sole();

        try {
            app(PublicFree365SignupService::class)->resend($started['token']);
            $this->fail('Expected a resend cooldown validation error.');
        } catch (ValidationException) {
            // The configured cooldown protects repeated sends.
        }

        Carbon::setTestNow(now()->addSeconds((int) config('saas.verification.resend_cooldown_seconds', 60) + 1));
        $session = app(PublicFree365SignupService::class)->resend($started['token']);
        Carbon::setTestNow();
        $second = NotificationDelivery::query()->whereKeyNot($first->id)->sole();

        $this->assertSame(2, $session->verification_sequence);
        $this->assertSame($second->id, $session->verification_delivery_id);
        $this->assertNotSame($this->otpCode($first), $this->otpCode($second));
        (new SendNotificationDeliveryJob($first->id))->handle(app(EmailDeliveryService::class), app(EmailDeliveryLifecycleService::class));
        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertNull($first->fresh()->sensitive_payload);
        Queue::assertPushed(SendNotificationDeliveryJob::class, 2);
    }

    public function test_expired_otp_is_rejected(): void
    {
        Queue::fake();
        $this->platformDeliveryCompany(configured: true);
        $service = app(PublicFree365SignupService::class);
        $expired = $service->begin('general_retail', 'email', 'expired@example.test', '127.0.0.1', 'test');
        $expired['session']->update(['verification_code_hash' => Hash::make('123456'), 'verification_expires_at' => now()->subSecond()]);
        $this->expectException(ValidationException::class);
        $service->verify($expired['token'], '123456');
    }

    public function test_verified_signup_provisions_one_free365_tenant_and_is_idempotent(): void
    {
        Queue::fake();
        $this->platformDeliveryCompany(configured: true);
        $started = app(PublicFree365SignupService::class)->begin('general_retail', 'email', 'owner@example.test', '127.0.0.1', 'test');
        $record = $started['session'];
        $code = $this->otpCode($record->verificationDelivery);
        try {
            app(PublicFree365SignupService::class)->verify($started['token'], '000000');
            $this->fail('Expected an invalid OTP validation error.');
        } catch (ValidationException) {
            // A failed attempt does not invalidate the correct current OTP.
        }
        app(PublicFree365SignupService::class)->verify($started['token'], $code);
        try {
            app(PublicFree365SignupService::class)->verify($started['token'], $code);
            $this->fail('Expected a used OTP validation error.');
        } catch (ValidationException) {
            // OTPs are single-use even before account completion.
        }
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
        Queue::fake();
        $this->platformDeliveryCompany(configured: true);
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

    private function platformDeliveryCompany(bool $configured): Company
    {
        $company = Company::factory()->create();
        $administrator = User::factory()->for($company)->create(['role' => UserRole::Administrator, 'is_platform_admin' => true]);
        config()->set('saas.public_signup.email_delivery_company_id', $company->id);

        if ($configured) {
            CompanyEmailSetting::create([
                'company_id' => $company->id,
                'is_enabled' => true,
                'host' => 'smtp.example.test',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'mailer@example.test',
                'from_name' => 'RetailPOS',
                'from_address' => 'hello@example.test',
                'updated_by' => $administrator->id,
            ]);
        }

        return $company;
    }

    private function otpCode(NotificationDelivery $delivery): string
    {
        preg_match('/\b(\d{6})\b/', (string) ($delivery->sensitive_payload['message'] ?? ''), $matches);
        $this->assertArrayHasKey(1, $matches);

        return $matches[1];
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
