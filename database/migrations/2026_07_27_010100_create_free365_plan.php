<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $code = config('saas.free365_plan_code', 'free-365');
        DB::table('saas_plans')->updateOrInsert(['code' => $code], [
            'name' => 'Free 365',
            'description' => 'A 365-day introductory RetailPOS package with one outlet, one active user, and 25 finalised invoices per calendar month.',
            'status' => 'active',
            'billing_interval' => 'yearly',
            'currency' => 'INR',
            'base_price' => 0,
            'setup_fee' => 0,
            'tax_percentage' => 0,
            'trial_days' => 0,
            'grace_period_days' => 0,
            'sort_order' => 5,
            'is_public' => false,
            'is_recommended' => false,
            'is_custom' => false,
            'notes' => 'No credit card. Automatic renewal is disabled. Expired accounts retain read-only access.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $planId = DB::table('saas_plans')->where('code', $code)->value('id');
        $features = [
            'pos', 'sales_invoices', 'inventory', 'crm', 'gst_compliance',
            'pos.billing', 'sales.invoices', 'inventory.basic', 'customers.basic', 'dashboard.basic',
        ];
        foreach ($features as $feature) {
            DB::table('saas_plan_features')->updateOrInsert(
                ['saas_plan_id' => $planId, 'feature_key' => $feature],
                ['is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
        foreach (['users' => 1, 'branches' => 1, 'monthly_invoices' => 25] as $key => $limit) {
            DB::table('saas_plan_limits')->updateOrInsert(
                ['saas_plan_id' => $planId, 'limit_key' => $key],
                ['limit_value' => $limit, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        // Existing subscriptions snapshot their package state. This release is additive.
    }
};
