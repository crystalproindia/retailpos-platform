<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\User;
use App\Services\Saas\EntitlementService;
use App\Services\Saas\SubscriptionService;
use App\Services\Saas\TenantOnboardingService;
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
