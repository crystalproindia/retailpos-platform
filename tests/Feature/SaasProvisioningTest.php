<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\AccountVerification;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\SaasIndustry;
use App\Models\SaasPlan;
use App\Models\User;
use App\Services\Saas\AccountVerificationService;
use App\Services\Saas\EntitlementService;
use App\Services\Saas\SaasLifecycleService;
use App\Services\Saas\SubscriptionService;
use App\Services\Saas\TenantProvisioningService;
use App\Services\Saas\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaasProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_can_provision_a_free365_account_atomically(): void
    {
        $actor = $this->platformAdministrator();
        $plan = $this->freePlan();
        $result = app(TenantProvisioningService::class)->provision([
            'idempotency_key' => '7c7cd8cf-fc85-442b-a68f-025acfe18cf0',
            'owner_name' => 'Asha Owner',
            'mobile' => '9876543210',
            'password' => 'password-with-enough-length',
            'email' => 'asha@example.test',
            'industry' => 'general_retail',
            'saas_plan_id' => $plan->id,
        ], $actor);

        $company = Company::findOrFail($result->company_id);
        $owner = $company->users()->firstOrFail();
        $this->assertSame('Your Store Name', $company->name);
        $this->assertSame('+919876543210', $owner->mobile);
        $this->assertSame('pending', $owner->verification_status);
        $this->assertTrue(Hash::check('password-with-enough-length', $owner->password));
        $this->assertDatabaseHas('branches', ['company_id' => $company->id, 'code' => 'MAIN', 'is_primary' => true]);
        $this->assertDatabaseHas('saas_subscriptions', ['company_id' => $company->id, 'saas_plan_id' => $plan->id, 'auto_renew' => false]);
        $this->assertDatabaseMissing('audit_logs', ['properties' => 'password-with-enough-length']);
    }

    public function test_disabled_industry_is_rejected_without_creating_a_tenant(): void
    {
        $actor = $this->platformAdministrator();
        $plan = $this->freePlan();
        app(\App\Services\Saas\IndustryRegistry::class)->enabled();
        SaasIndustry::where('key', 'general_retail')->update(['is_enabled' => false]);
        try {
            app(TenantProvisioningService::class)->provision([
                'idempotency_key' => '7c7cd8cf-fc85-442b-a68f-025acfe18cf1', 'owner_name' => 'Asha Owner', 'mobile' => '9876543211',
                'password' => 'password-with-enough-length', 'industry' => 'general_retail', 'saas_plan_id' => $plan->id,
            ], $actor);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('saas_subscriptions', 0);
        }
    }

    public function test_email_otp_is_hashed_single_use_and_attempt_limited(): void
    {
        [, $user] = $this->tenant();
        $service = app(AccountVerificationService::class);
        $code = $service->issue($user, 'email');
        $record = AccountVerification::firstOrFail();
        $this->assertNotSame($code, $record->code_hash);
        $service->verify($user, 'email', $code);
        $this->assertSame('verified', $user->fresh()->verification_status);
        $this->expectException(ValidationException::class);
        $service->verify($user, 'email', $code);
    }

    public function test_free365_expires_after_365_days_without_creating_another_subscription(): void
    {
        Carbon::setTestNow('2026-07-01 10:00:00');
        [$company, $user] = $this->tenant();
        $subscription = app(SubscriptionService::class)->create($company, $this->freePlan(), $user);
        $this->assertSame('2027-07-01', $subscription->renewal_date->toDateString());
        Carbon::setTestNow('2027-07-02 10:00:00');
        app(SaasLifecycleService::class)->processRenewals();
        $this->assertSame('expired', $subscription->fresh()->status);
        $this->assertSame(1, $company->saasSubscriptions()->count());
        Carbon::setTestNow();
    }

    public function test_expired_free365_tenant_keeps_read_access_but_writes_are_blocked_when_enforcement_is_enabled(): void
    {
        config()->set('saas.enforcement_enabled', true);
        [$company, $user] = $this->tenant();
        $subscription = app(SubscriptionService::class)->create($company, $this->freePlan(), $user);
        app(SubscriptionService::class)->transition($subscription, 'expired', null, 'Free 365 ended.');
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->post(route('pos.checkout'), [])->assertForbidden();
    }

    public function test_monthly_invoice_meter_excludes_drafts_and_cancelled_records_and_is_tenant_scoped(): void
    {
        [$company, $user] = $this->tenant();
        $plan = $this->freePlan();
        app(SubscriptionService::class)->create($company, $plan, $user);
        foreach ([InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Issued] as $index => $status) {
            CrmInvoice::create(['company_id' => $company->id, 'invoice_number' => 'T-'.$index, 'status' => $status, 'created_by' => $user->id]);
        }
        [$other] = $this->tenant();
        CrmInvoice::create(['company_id' => $other->id, 'invoice_number' => 'OTHER-1', 'status' => InvoiceStatus::Issued]);
        $usage = app(UsageService::class)->invoiceUsage($company);
        $this->assertSame(1, $usage['used']);
        $this->assertSame(24, $usage['remaining']);
    }

    public function test_free365_blocks_the_twenty_sixth_finalised_invoice(): void
    {
        [$company, $user] = $this->tenant();
        app(SubscriptionService::class)->create($company, $this->freePlan(), $user);
        foreach (range(1, 25) as $number) {
            CrmInvoice::create(['company_id' => $company->id, 'invoice_number' => 'LIMIT-'.$number, 'status' => InvoiceStatus::Issued, 'created_by' => $user->id]);
        }
        $this->expectException(ValidationException::class);
        app(UsageService::class)->assertWithinLimit($company, 'monthly_invoices');
    }

    public function test_permission_and_entitlement_are_both_required(): void
    {
        config()->set('saas.enforcement_enabled', true);
        [$company, $administrator] = $this->tenant();
        $plan = $this->freePlan();
        app(SubscriptionService::class)->create($company, $plan, $administrator);
        $this->assertTrue(app(EntitlementService::class)->allows($company, 'dashboard.basic'));
        $this->assertFalse(app(EntitlementService::class)->allows($company, 'ai.assistant'));
        $this->actingAs($administrator)->get(route('saas.tenants.create'))->assertForbidden();
    }

    private function freePlan(): SaasPlan
    {
        $plan = SaasPlan::query()->firstOrCreate(['code' => config('saas.free365_plan_code')], ['name' => 'Free 365', 'status' => 'active', 'billing_interval' => 'yearly', 'currency' => 'INR']);
        foreach (['dashboard.basic', 'pos.billing', 'sales.invoices', 'inventory.basic', 'customers.basic'] as $feature) $plan->features()->updateOrCreate(['feature_key' => $feature], ['is_enabled' => true]);
        foreach (['users' => 1, 'branches' => 1, 'monthly_invoices' => 25] as $key => $value) $plan->limits()->updateOrCreate(['limit_key' => $key], ['limit_value' => $value]);
        return $plan;
    }

    /** @return array{Company, User} */
    private function tenant(bool $platform = false): array
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Administrator, 'is_platform_admin' => $platform]);
        return [$company, $user];
    }

    private function platformAdministrator(): User
    {
        return $this->tenant(true)[1];
    }
}
