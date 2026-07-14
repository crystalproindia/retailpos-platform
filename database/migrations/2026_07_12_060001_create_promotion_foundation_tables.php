<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('campaign_type')->default('manual');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status', 'is_active']);
        });

        Schema::create('promotion_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('promotion_campaigns')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('promotion_type');
            $table->string('discount_type')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('stackable')->default(false);
            $table->boolean('exclusive')->default(false);
            $table->boolean('requires_coupon')->default(false);
            $table->boolean('auto_apply')->default(true);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('usage_limit_per_day')->nullable();
            $table->decimal('minimum_bill_amount', 14, 2)->nullable();
            $table->decimal('minimum_quantity', 14, 3)->nullable();
            $table->decimal('maximum_discount_amount', 14, 2)->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status', 'is_active', 'priority']);
        });

        Schema::create('promotion_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->string('condition_type');
            $table->string('operator')->default('equals');
            $table->text('value')->nullable();
            $table->json('value_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['promotion_rule_id', 'condition_type']);
        });

        Schema::create('promotion_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->string('action_type');
            $table->decimal('discount_value', 14, 2)->nullable();
            $table->decimal('discount_percentage', 8, 3)->nullable();
            $table->decimal('fixed_price', 14, 2)->nullable();
            $table->decimal('buy_quantity', 14, 3)->nullable();
            $table->decimal('get_quantity', 14, 3)->nullable();
            $table->foreignId('free_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('applies_to_same_product')->default(true);
            $table->decimal('maximum_free_quantity', 14, 3)->nullable();
            $table->decimal('maximum_discount_amount', 14, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('promotion_product_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('include_or_exclude')->default('include');
            $table->timestamps();
            $table->unique(['promotion_rule_id', 'product_id'], 'promo_prod_target_rule_product_uq');
        });

        Schema::create('promotion_category_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('inventory_categories')->cascadeOnDelete();
            $table->string('include_or_exclude')->default('include');
            $table->timestamps();
            $table->unique(['promotion_rule_id', 'category_id'], 'promo_cat_target_rule_category_uq');
        });

        Schema::create('promotion_brand_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('inventory_brands')->cascadeOnDelete();
            $table->string('include_or_exclude')->default('include');
            $table->timestamps();
            $table->unique(['promotion_rule_id', 'brand_id']);
        });

        Schema::create('promotion_variant_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('include_or_exclude')->default('include');
            $table->timestamps();
            $table->unique(['promotion_rule_id', 'product_id'], 'promo_var_target_rule_product_uq');
        });

        Schema::create('promotion_branch_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('include_or_exclude')->default('include');
            $table->timestamps();
            $table->unique(['promotion_rule_id', 'branch_id']);
        });

        Schema::create('promotion_channel_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_channel_id')->constrained('sales_channels')->cascadeOnDelete();
            $table->string('include_or_exclude')->default('include');
            $table->timestamps();
            $table->unique(['promotion_rule_id', 'sales_channel_id'], 'promo_channel_target_rule_channel_uq');
        });

        Schema::create('promotion_coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->text('description')->nullable();
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('promotion_coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('cart_reference')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->timestamp('redeemed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'promotion_coupon_id'], 'promo_coupon_redemp_company_coupon_idx');
        });

        Schema::create('promotion_rule_usage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('cart_reference')->nullable();
            $table->date('usage_date');
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('quantity_affected', 14, 3)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'promotion_rule_id', 'usage_date'], 'promo_rule_usage_company_rule_date_idx');
        });

        Schema::create('promotion_simulations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->json('cart_payload');
            $table->json('result_payload');
            $table->decimal('total_before_discount', 14, 2)->default(0);
            $table->decimal('total_discount', 14, 2)->default(0);
            $table->decimal('total_after_discount', 14, 2)->default(0);
            $table->timestamp('simulated_at');
            $table->timestamps();
            $table->index(['company_id', 'simulated_at']);
        });

        Schema::create('promotion_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('allow_stacking')->default(true);
            $table->string('default_priority_strategy')->default('priority_then_benefit');
            $table->boolean('allow_coupon_with_auto_discount')->default(true);
            $table->decimal('max_discount_percentage_per_bill', 8, 3)->nullable();
            $table->decimal('max_discount_amount_per_bill', 14, 2)->nullable();
            $table->boolean('require_approval_for_promotions')->default(false);
            $table->decimal('require_approval_above_discount_percentage', 8, 3)->nullable();
            $table->decimal('require_approval_above_discount_amount', 14, 2)->nullable();
            $table->boolean('show_discount_breakup_on_bill_future')->default(true);
            $table->timestamps();
            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_settings');
        Schema::dropIfExists('promotion_simulations');
        Schema::dropIfExists('promotion_rule_usage');
        Schema::dropIfExists('promotion_coupon_redemptions');
        Schema::dropIfExists('promotion_coupons');
        Schema::dropIfExists('promotion_channel_targets');
        Schema::dropIfExists('promotion_branch_targets');
        Schema::dropIfExists('promotion_variant_targets');
        Schema::dropIfExists('promotion_brand_targets');
        Schema::dropIfExists('promotion_category_targets');
        Schema::dropIfExists('promotion_product_targets');
        Schema::dropIfExists('promotion_actions');
        Schema::dropIfExists('promotion_conditions');
        Schema::dropIfExists('promotion_rules');
        Schema::dropIfExists('promotion_campaigns');
    }
};
