<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const GRANDFATHERED_PLAN_CODE = 'existing-tenant-access';

    public function up(): void
    {
        $this->addCompanyColumns();
        $this->addSubscriptionColumns();

        $now = now();
        $features = collect(config('saas.features', []))->mapWithKeys(fn (string $feature) => [$feature => true])->all();
        $limits = collect(config('saas.usage_limits', []))->mapWithKeys(fn (string $key) => [$key => null])->all();
        $code = self::GRANDFATHERED_PLAN_CODE;

        DB::table('saas_plans')->updateOrInsert(
            ['code' => $code],
            [
                'name' => 'Existing tenant access',
                'description' => 'Safe grandfathered access for tenants created before SaaS billing rollout.',
                'status' => 'active',
                'billing_interval' => 'manual',
                'currency' => 'INR',
                'base_price' => 0,
                'setup_fee' => 0,
                'tax_percentage' => 0,
                'trial_days' => 0,
                'grace_period_days' => 0,
                'sort_order' => 0,
                'is_public' => false,
                'is_recommended' => false,
                'is_custom' => true,
                'notes' => 'Created automatically during SaaS foundation migration.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $planId = (int) DB::table('saas_plans')->where('code', $code)->value('id');

        foreach ($features as $key => $enabled) {
            DB::table('saas_plan_features')->updateOrInsert(
                ['saas_plan_id' => $planId, 'feature_key' => $key],
                ['is_enabled' => $enabled, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        foreach ($limits as $key => $limit) {
            DB::table('saas_plan_limits')->updateOrInsert(
                ['saas_plan_id' => $planId, 'limit_key' => $key],
                ['limit_value' => $limit, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        if (! DB::table('saas_plan_versions')->where('saas_plan_id', $planId)->where('version', 1)->exists()) {
            DB::table('saas_plan_versions')->insert([
                'saas_plan_id' => $planId,
                'version' => 1,
                'snapshot' => json_encode([
                    'plan_id' => $planId,
                    'plan_code' => $code,
                    'name' => 'Existing tenant access',
                    'price' => 0,
                    'setup_fee' => 0,
                    'tax_percentage' => 0,
                    'billing_interval' => 'manual',
                    'features' => $features,
                    'limits' => $limits,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('companies')->orderBy('id')->each(function (object $company) use ($planId, $features, $limits, $now): void {
            DB::transaction(function () use ($company, $planId, $features, $limits, $now): void {
                DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();

                $hasCurrentSubscription = DB::table('saas_subscriptions')
                    ->where('company_id', $company->id)
                    ->whereIn('status', ['trialing', 'active', 'grace_period', 'past_due', 'suspended'])
                    ->exists();

                if ($hasCurrentSubscription) {
                    return;
                }

                $today = now();
                DB::table('saas_subscriptions')->insert([
                    'company_id' => $company->id,
                    'saas_plan_id' => $planId,
                    'subscription_number' => 'SUB-LEGACY-'.strtoupper(Str::ulid()),
                    'status' => 'active',
                    'billing_interval' => 'manual',
                    'currency' => $company->currency ?: 'INR',
                    'price_snapshot' => 0,
                    'tax_snapshot' => 0,
                    'setup_fee_snapshot' => 0,
                    'feature_snapshot' => json_encode($features, JSON_THROW_ON_ERROR),
                    'limit_snapshot' => json_encode($limits, JSON_THROW_ON_ERROR),
                    'starts_at' => $today->toDateString(),
                    'current_period_starts_at' => $today->toDateString(),
                    'current_period_ends_at' => $today->copy()->addYear()->toDateString(),
                    'renewal_date' => $today->copy()->addYear()->toDateString(),
                    'billing_method' => 'complimentary',
                    'auto_renew' => false,
                    'internal_notes' => 'Automatically grandfathered during SaaS foundation rollout.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
        });
    }

    private function addCompanyColumns(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        foreach ([
            'trade_name' => fn (Blueprint $table) => $table->string('trade_name')->nullable()->after('legal_name'),
            'city' => fn (Blueprint $table) => $table->string('city', 120)->nullable()->after('address'),
            'state' => fn (Blueprint $table) => $table->string('state', 120)->nullable()->after('city'),
            'country' => fn (Blueprint $table) => $table->string('country', 120)->nullable()->after('state'),
            'postal_code' => fn (Blueprint $table) => $table->string('postal_code', 40)->nullable()->after('country'),
            'tax_registration_type' => fn (Blueprint $table) => $table->string('tax_registration_type', 48)->nullable()->after('currency'),
            'industry' => fn (Blueprint $table) => $table->string('industry', 120)->nullable()->after('tax_registration_type'),
            'billing_contact_name' => fn (Blueprint $table) => $table->string('billing_contact_name')->nullable()->after('industry'),
            'billing_contact_email' => fn (Blueprint $table) => $table->string('billing_contact_email')->nullable()->after('billing_contact_name'),
        ] as $column => $addColumn) {
            if (! Schema::hasColumn('companies', $column)) {
                Schema::table('companies', $addColumn);
            }
        }
    }

    private function addSubscriptionColumns(): void
    {
        if (! Schema::hasTable('saas_subscriptions')) {
            return;
        }

        if (! Schema::hasColumn('saas_subscriptions', 'pending_saas_plan_id')) {
            Schema::table('saas_subscriptions', function (Blueprint $table): void {
                $table->foreignId('pending_saas_plan_id')->nullable()->after('saas_plan_id')->constrained('saas_plans')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('saas_subscriptions', 'pending_change_at')) {
            Schema::table('saas_subscriptions', function (Blueprint $table): void {
                $table->date('pending_change_at')->nullable()->after('renewal_date');
            });
        }
        if (! Schema::hasColumn('saas_subscriptions', 'pending_change_reason')) {
            Schema::table('saas_subscriptions', function (Blueprint $table): void {
                $table->text('pending_change_reason')->nullable()->after('internal_notes');
            });
        }
    }

    public function down(): void
    {
        // This migration is additive. Production recovery must never rely on rollback.
    }
};
