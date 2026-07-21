<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\SaasReseller;
use App\Models\SaasUsageSnapshot;
use App\Models\User;
use App\Services\Saas\EntitlementService;
use App\Services\Saas\PlanChangeService;
use App\Services\Saas\SaasLifecycleService;
use App\Services\Saas\SubscriptionService;
use App\Services\Saas\TenantOnboardingService;
use App\Services\Saas\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasSubscriptionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_subscription_snapshots_plan_entitlements_without_mutating_the_plan(): void
    {
        [$company, $administrator] = $this->tenant();
        $plan = $this->plan(['trial_days' => 14]);
        $plan->features()->create(['feature_key' => 'pos', 'is_enabled' => true]);
        $plan->limits()->create(['limit_key' => 'users', 'limit_value' => 5]);

        $subscription = app(SubscriptionService::class)->create($company, $plan, $administrator);

        $this->assertSame('trialing', $subscription->status);
        $this->assertTrue($subscription->feature_snapshot['pos']);
        $this->assertSame(5, $subscription->limit_snapshot['users']);

        $plan->features()->update(['is_enabled' => false]);

        $this->assertTrue(app(EntitlementService::class)->allows($company, 'pos'));
    }

    public function test_platform_administration_is_not_granted_by_the_tenant_administrator_role(): void
    {
        [, $tenantAdministrator] = $this->tenant();

        $this->actingAs($tenantAdministrator)->get(route('saas.dashboard'))->assertForbidden();

        $tenantAdministrator->update(['is_platform_admin' => true]);

        $this->actingAs($tenantAdministrator)->get(route('saas.dashboard'))->assertOk();
    }

    public function test_suspended_subscription_loses_entitlements_and_reactivation_clears_the_cached_result(): void
    {
        [$company, $administrator] = $this->tenant();
        $plan = $this->plan();
        $plan->features()->create(['feature_key' => 'inventory', 'is_enabled' => true]);
        $subscription = app(SubscriptionService::class)->create($company, $plan, $administrator);

        $this->assertTrue(app(EntitlementService::class)->allows($company, 'inventory'));

        app(SubscriptionService::class)->transition($subscription, 'suspended', $administrator, 'Account review');
        $this->assertFalse(app(EntitlementService::class)->allows($company, 'inventory'));

        app(SubscriptionService::class)->transition($subscription, 'active', $administrator, 'Review completed');
        $this->assertTrue(app(EntitlementService::class)->allows($company, 'inventory'));
    }

    public function test_tenant_administrator_can_view_only_its_own_subscription_portal(): void
    {
        [$company, $administrator] = $this->tenant();
        $plan = $this->plan();
        app(SubscriptionService::class)->create($company, $plan, $administrator);

        $this->actingAs($administrator)
            ->get(route('account.subscription.index'))
            ->assertOk()
            ->assertSee($company->name)
            ->assertSee('Subscription & Usage');
    }

    public function test_platform_administrator_can_create_a_versioned_plan(): void
    {
        [, $platformAdministrator] = $this->tenant(['is_platform_admin' => true]);

        $this->actingAs($platformAdministrator)->post(route('saas.plans.store'), [
            'name' => 'Retail Growth',
            'code' => 'retail-growth',
            'status' => 'active',
            'billing_interval' => 'monthly',
            'currency' => 'INR',
            'base_price' => 1999,
            'features' => ['pos' => true, 'inventory' => true],
            'limits' => ['users' => 5, 'products' => 500],
        ])->assertRedirect();

        $plan = SaasPlan::where('code', 'retail-growth')->firstOrFail();
        $this->assertSame(1, $plan->versions()->count());
        $this->assertSame(5, $plan->limits()->where('limit_key', 'users')->value('limit_value'));
    }

    public function test_tenant_onboarding_is_idempotent_and_creates_one_company_branch_user_and_subscription(): void
    {
        [, $platformAdministrator] = $this->tenant(['is_platform_admin' => true]);
        $plan = $this->plan();
        $payload = [
            'idempotency_key' => '5de9fc77-53da-49d8-b937-628e883de96d',
            'legal_name' => 'Northstar Retail Private Limited',
            'trade_name' => 'Northstar Retail',
            'email' => 'office@northstar.test',
            'phone' => '9876543210',
            'country' => 'India',
            'state' => 'Karnataka',
            'city' => 'Bengaluru',
            'address' => 'MG Road',
            'postal_code' => '560001',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'tax_registration_type' => 'regular',
            'gstin' => null,
            'industry' => 'Retail',
            'saas_plan_id' => $plan->id,
            'billing_method' => 'manual',
            'branch_name' => 'Flagship Store',
            'admin_name' => 'Northstar Admin',
            'admin_email' => 'admin@northstar.test',
            'admin_password' => 'password-with-enough-length',
            'billing_contact_name' => null,
            'billing_contact_email' => null,
        ];

        $first = app(TenantOnboardingService::class)->complete($payload, $platformAdministrator);
        $second = app(TenantOnboardingService::class)->complete($payload, $platformAdministrator);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('companies', 2);
        $this->assertDatabaseCount('saas_subscriptions', 1);
        $this->assertDatabaseHas('branches', ['company_id' => $first->company_id, 'name' => 'Flagship Store']);
        $this->assertDatabaseHas('users', ['company_id' => $first->company_id, 'email' => 'admin@northstar.test']);
    }

    public function test_trial_reminders_and_expiration_are_idempotent(): void
    {
        [$company, $administrator] = $this->tenant();
        $subscription = app(SubscriptionService::class)->create($company, $this->plan(['trial_days' => 1, 'grace_period_days' => 1]), $administrator);
        $subscription->update(['trial_ends_at' => today()->addDay(), 'grace_period_ends_at' => today()->addDays(2)]);

        app(SaasLifecycleService::class)->processTrials();
        app(SaasLifecycleService::class)->processTrials();
        $this->assertSame(1, $subscription->fresh()->events()->where('event_key', 'TrialEndingIn1Days')->count());

        $subscription->update(['trial_ends_at' => today()->subDay(), 'grace_period_ends_at' => today()->subDay()]);
        app(SaasLifecycleService::class)->processTrials();
        app(SaasLifecycleService::class)->processTrials();
        $this->assertSame('expired', $subscription->fresh()->status);
        $this->assertSame(1, $subscription->fresh()->events()->where('event_key', 'SubscriptionExpired')->count());
    }

    public function test_renewal_is_idempotent_and_reactivates_a_suspended_subscription(): void
    {
        [$company, $administrator] = $this->tenant();
        $subscription = app(SubscriptionService::class)->create($company, $this->plan(), $administrator);
        app(SubscriptionService::class)->transition($subscription, 'suspended', $administrator, 'Test');

        $first = app(SubscriptionService::class)->renew($subscription, $administrator, 'offline', 'REF-1', 'renewal-test');
        $second = app(SubscriptionService::class)->renew($subscription, $administrator, 'offline', 'REF-1', 'renewal-test');

        $this->assertSame('active', $first->status);
        $this->assertSame($first->renewal_date?->toDateString(), $second->renewal_date?->toDateString());
        $this->assertSame(1, $subscription->fresh()->events()->where('idempotency_key', 'renewal-test')->count());
        $this->assertTrue(app(EntitlementService::class)->active($company));
    }

    public function test_usage_recalculation_persists_one_snapshot_per_metric_and_limit_override_expires(): void
    {
        [$company] = $this->tenant();
        $plan = $this->plan();
        $plan->limits()->create(['limit_key' => 'users', 'limit_value' => 1]);
        app(SubscriptionService::class)->create($company, $plan, null);
        app(UsageService::class)->recalculate($company);
        app(UsageService::class)->recalculate($company);
        $this->assertSame(count(config('saas.usage_limits')), SaasUsageSnapshot::where('company_id', $company->id)->count());

        $company->saasSubscriptions()->first()->company->saasSubscriptions();
        \App\Models\SaasTenantOverride::create(['company_id' => $company->id, 'override_type' => 'limit', 'key' => 'users', 'value' => ['value' => 5], 'reason' => 'Temporary support approval', 'ends_at' => today()->subDay()]);
        $this->assertSame(1, app(EntitlementService::class)->limit($company, 'users'));
    }

    public function test_plan_change_detects_downgrade_conflicts_and_can_be_cancelled_without_deleting_records(): void
    {
        [$company, $administrator] = $this->tenant();
        User::factory()->for($company)->create(['role' => UserRole::Staff]);
        $current = $this->plan();
        $current->limits()->create(['limit_key' => 'users', 'limit_value' => null]);
        $subscription = app(SubscriptionService::class)->create($company, $current, $administrator);
        $downgrade = $this->plan();
        $downgrade->limits()->create(['limit_key' => 'users', 'limit_value' => 1]);

        $service = app(PlanChangeService::class);
        $this->assertArrayHasKey('users', $service->conflicts($subscription, $downgrade));
        $service->schedule($subscription, $downgrade, $administrator, false, 'At renewal');
        $this->assertSame($downgrade->id, $subscription->fresh()->pending_saas_plan_id);
        $service->cancelScheduledChange($subscription->fresh(), $administrator);
        $this->assertNull($subscription->fresh()->pending_saas_plan_id);
        $this->assertSame(2, User::where('company_id', $company->id)->count());
    }

    public function test_white_label_requires_entitlement_and_stays_tenant_scoped(): void
    {
        [$company, $administrator] = $this->tenant();
        $plan = $this->plan();
        app(SubscriptionService::class)->create($company, $plan, $administrator);
        $this->actingAs($administrator)->get(route('account.subscription.white-label.edit'))->assertForbidden();

        $planWithWhiteLabel = $this->plan();
        $planWithWhiteLabel->features()->create(['feature_key' => 'white_label', 'is_enabled' => true]);
        app(SubscriptionService::class)->transition($company->saasSubscriptions()->first(), 'cancelled', $administrator);
        app(SubscriptionService::class)->create($company, $planWithWhiteLabel, $administrator);
        $this->actingAs($administrator)->put(route('account.subscription.white-label.update'), [
            'display_name' => 'Northstar Retail', 'primary_color' => '#112233', 'secondary_color' => '#445566', 'custom_domain_status' => 'pending', 'show_powered_by' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('settings', ['company_id' => $company->id, 'group' => 'saas_white_label', 'key' => 'display_name']);
    }

    public function test_resellers_are_platform_only_and_tenant_assignment_is_audited(): void
    {
        [$company, $tenantAdministrator] = $this->tenant();
        $this->actingAs($tenantAdministrator)->get(route('saas.resellers.index'))->assertForbidden();
        $tenantAdministrator->update(['is_platform_admin' => true]);

        $reseller = SaasReseller::create(['partner_code' => 'NORTHSTAR-PARTNER', 'company_name' => 'Northstar Partner', 'status' => 'active', 'owner_id' => $tenantAdministrator->id]);
        $this->actingAs($tenantAdministrator)->post(route('saas.resellers.tenants.assign', $reseller), ['company_id' => $company->id])->assertRedirect();
        $this->assertDatabaseHas('saas_reseller_tenant_assignments', ['saas_reseller_id' => $reseller->id, 'company_id' => $company->id]);
    }

    /** @return array{Company, User} */
    private function tenant(array $userAttributes = []): array
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $administrator = User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => UserRole::Administrator,
        ] + $userAttributes);

        return [$company, $administrator];
    }

    private function plan(array $attributes = []): SaasPlan
    {
        return SaasPlan::create(array_merge([
            'name' => 'Growth',
            'code' => 'growth-'.str()->lower(str()->random(6)),
            'status' => 'active',
            'billing_interval' => 'monthly',
            'currency' => 'INR',
            'base_price' => 1000,
            'trial_days' => 0,
            'grace_period_days' => 3,
        ], $attributes));
    }
}
