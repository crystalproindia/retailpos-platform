<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_platform_admin')->default(false)->after('is_active');
        });

        Schema::create('saas_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name'); $table->string('code', 80)->unique(); $table->text('description')->nullable();
            $table->string('status', 24)->default('draft'); $table->string('billing_interval', 24)->default('monthly'); $table->string('currency', 3)->default('INR');
            $table->decimal('base_price', 14, 2)->default(0); $table->decimal('setup_fee', 14, 2)->default(0); $table->decimal('tax_percentage', 8, 3)->default(0);
            $table->unsignedSmallInteger('trial_days')->default(0); $table->unsignedSmallInteger('grace_period_days')->default(0); $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_public')->default(false); $table->boolean('is_recommended')->default(false); $table->boolean('is_custom')->default(false); $table->text('notes')->nullable();
            $table->date('effective_from')->nullable(); $table->date('effective_until')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('saas_plan_features', function (Blueprint $table): void {
            $table->id(); $table->foreignId('saas_plan_id')->constrained()->cascadeOnDelete(); $table->string('feature_key', 100); $table->boolean('is_enabled')->default(true); $table->timestamps();
            $table->unique(['saas_plan_id', 'feature_key'], 'saas_plan_feature_uq');
        });
        Schema::create('saas_plan_limits', function (Blueprint $table): void {
            $table->id(); $table->foreignId('saas_plan_id')->constrained()->cascadeOnDelete(); $table->string('limit_key', 100); $table->unsignedBigInteger('limit_value')->nullable(); $table->timestamps();
            $table->unique(['saas_plan_id', 'limit_key'], 'saas_plan_limit_uq');
        });
        Schema::create('saas_plan_versions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('saas_plan_id')->constrained()->restrictOnDelete(); $table->unsignedInteger('version'); $table->json('snapshot'); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->unique(['saas_plan_id', 'version'], 'saas_plan_version_uq');
        });
        Schema::create('saas_subscriptions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('saas_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subscription_number', 80)->unique(); $table->string('status', 32); $table->string('billing_interval', 24); $table->string('currency', 3);
            $table->decimal('price_snapshot', 14, 2)->default(0); $table->decimal('tax_snapshot', 8, 3)->default(0); $table->decimal('setup_fee_snapshot', 14, 2)->default(0);
            $table->json('feature_snapshot'); $table->json('limit_snapshot'); $table->date('trial_starts_at')->nullable(); $table->date('trial_ends_at')->nullable();
            $table->date('starts_at')->nullable(); $table->date('current_period_starts_at')->nullable(); $table->date('current_period_ends_at')->nullable(); $table->date('renewal_date')->nullable(); $table->date('grace_period_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable(); $table->date('cancellation_effective_at')->nullable(); $table->timestamp('suspended_at')->nullable(); $table->timestamp('reactivated_at')->nullable();
            $table->string('billing_method', 24)->default('manual'); $table->string('provider_name', 80)->nullable(); $table->string('provider_reference', 160)->nullable(); $table->boolean('auto_renew')->default(false); $table->text('internal_notes')->nullable(); $table->timestamps();
            $table->index(['company_id', 'status'], 'saas_subscription_company_status_idx');
        });
        Schema::create('saas_subscription_events', function (Blueprint $table): void {
            $table->id(); $table->foreignId('saas_subscription_id')->constrained()->cascadeOnDelete(); $table->string('event_key', 100); $table->string('from_status', 32)->nullable(); $table->string('to_status', 32)->nullable(); $table->json('payload')->nullable(); $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('saas_tenant_overrides', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->string('override_type', 24); $table->string('key', 100); $table->json('value'); $table->string('reason', 1000); $table->date('starts_at')->nullable(); $table->date('ends_at')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->index(['company_id', 'override_type', 'key'], 'saas_tenant_override_lookup_idx');
        });
        Schema::create('saas_tenant_onboardings', function (Blueprint $table): void {
            $table->id(); $table->string('idempotency_key', 80)->unique(); $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('saas_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('draft'); $table->string('current_stage', 48)->default('business_details'); $table->json('payload'); $table->text('failure_reason')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('saas_tenant_onboardings'); Schema::dropIfExists('saas_tenant_overrides'); Schema::dropIfExists('saas_subscription_events'); Schema::dropIfExists('saas_subscriptions'); Schema::dropIfExists('saas_plan_versions'); Schema::dropIfExists('saas_plan_limits'); Schema::dropIfExists('saas_plan_features'); Schema::dropIfExists('saas_plans');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_admin'));
    }
};
