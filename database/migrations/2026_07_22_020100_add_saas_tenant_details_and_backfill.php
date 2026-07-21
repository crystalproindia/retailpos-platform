<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('trade_name')->nullable()->after('legal_name');
            $table->string('city', 120)->nullable()->after('address');
            $table->string('state', 120)->nullable()->after('city');
            $table->string('country', 120)->nullable()->after('state');
            $table->string('postal_code', 40)->nullable()->after('country');
            $table->string('tax_registration_type', 48)->nullable()->after('currency');
            $table->string('industry', 120)->nullable()->after('tax_registration_type');
            $table->string('billing_contact_name')->nullable()->after('industry');
            $table->string('billing_contact_email')->nullable()->after('billing_contact_name');
        });

        Schema::table('saas_subscriptions', function (Blueprint $table): void {
            $table->foreignId('pending_saas_plan_id')->nullable()->after('saas_plan_id')->constrained('saas_plans')->nullOnDelete();
            $table->date('pending_change_at')->nullable()->after('renewal_date');
            $table->text('pending_change_reason')->nullable()->after('internal_notes');
        });

        $now = now();
        $features = collect(config('saas.features', []))->mapWithKeys(fn (string $feature) => [$feature => true])->all();
        $limits = collect(config('saas.usage_limits', []))->mapWithKeys(fn (string $key) => [$key => null])->all();

        $planId = DB::table('saas_plans')->where('code', config('saas.grandfathered_plan_code'))->value('id');

        if (! $planId) {
            $planId = DB::table('saas_plans')->insertGetId([
                'name' => 'Existing tenant access',
                'code' => config('saas.grandfathered_plan_code'),
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
            ]);

            foreach ($features as $key => $enabled) {
                DB::table('saas_plan_features')->insert([
                    'saas_plan_id' => $planId,
                    'feature_key' => $key,
                    'is_enabled' => $enabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($limits as $key => $limit) {
                DB::table('saas_plan_limits')->insert([
                    'saas_plan_id' => $planId,
                    'limit_key' => $key,
                    'limit_value' => $limit,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('saas_plan_versions')->insert([
                'saas_plan_id' => $planId,
                'version' => 1,
                'snapshot' => json_encode([
                    'plan_id' => $planId,
                    'plan_code' => config('saas.grandfathered_plan_code'),
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
            $hasCurrentSubscription = DB::table('saas_subscriptions')
                ->where('company_id', $company->id)
                ->whereIn('status', ['trialing', 'active', 'grace_period', 'past_due', 'suspended'])
                ->exists();

            if ($hasCurrentSubscription) {
                return;
            }

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
                'starts_at' => now()->toDateString(),
                'current_period_starts_at' => now()->toDateString(),
                'current_period_ends_at' => now()->addYear()->toDateString(),
                'renewal_date' => now()->addYear()->toDateString(),
                'billing_method' => 'complimentary',
                'auto_renew' => false,
                'internal_notes' => 'Automatically grandfathered during SaaS foundation rollout.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('saas_subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pending_saas_plan_id');
            $table->dropColumn(['pending_change_at', 'pending_change_reason']);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['trade_name', 'city', 'state', 'country', 'postal_code', 'tax_registration_type', 'industry', 'billing_contact_name', 'billing_contact_email']);
        });
    }
};
