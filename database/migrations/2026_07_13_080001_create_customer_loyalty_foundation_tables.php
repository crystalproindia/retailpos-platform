<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_number'); $table->string('first_name'); $table->string('last_name')->nullable(); $table->string('display_name');
            $table->string('email')->nullable(); $table->string('phone')->nullable(); $table->string('alternate_phone')->nullable(); $table->string('whatsapp')->nullable();
            $table->string('gender')->nullable(); $table->date('date_of_birth')->nullable(); $table->date('anniversary_date')->nullable(); $table->string('customer_type')->default('retail'); $table->string('status')->default('active'); $table->string('source')->nullable();
            $table->string('tax_number')->nullable(); $table->string('gstin')->nullable(); $table->text('billing_address')->nullable(); $table->text('shipping_address')->nullable(); $table->string('city')->nullable(); $table->string('state')->nullable(); $table->string('country')->nullable(); $table->string('postal_code')->nullable(); $table->string('preferred_contact_method')->nullable(); $table->text('notes')->nullable(); $table->json('tags')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('last_purchase_at')->nullable(); $table->timestamp('last_return_at')->nullable();
            $table->decimal('total_purchase_amount', 14, 2)->default(0); $table->decimal('total_return_amount', 14, 2)->default(0); $table->unsignedInteger('total_orders_count')->default(0); $table->unsignedInteger('total_returns_count')->default(0); $table->integer('loyalty_points_balance')->default(0); $table->decimal('wallet_balance', 14, 2)->default(0); $table->decimal('credit_limit', 14, 2)->nullable(); $table->decimal('outstanding_balance', 14, 2)->default(0); $table->boolean('is_active')->default(true);
            $table->timestamps(); $table->softDeletes();
            $table->unique(['company_id', 'customer_number']); $table->unique(['company_id', 'email']); $table->index(['company_id', 'status', 'customer_type']); $table->index(['company_id', 'phone']);
        });
        Schema::create('customer_groups', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('slug'); $table->text('description')->nullable(); $table->decimal('discount_percentage', 8, 3)->nullable(); $table->decimal('loyalty_multiplier', 8, 3)->nullable(); $table->boolean('is_default')->default(false); $table->boolean('is_active')->default(true); $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->softDeletes(); $table->unique(['company_id', 'slug']);
        });
        Schema::create('customer_group_members', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete(); $table->timestamp('assigned_at'); $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete(); $table->text('notes')->nullable(); $table->timestamps(); $table->unique(['customer_id', 'customer_group_id']);
        });
        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); $table->string('type')->default('billing'); $table->string('name')->nullable(); $table->string('phone')->nullable(); $table->string('address_line_1'); $table->string('address_line_2')->nullable(); $table->string('city'); $table->string('state'); $table->string('country'); $table->string('postal_code'); $table->boolean('is_default')->default(false); $table->timestamps(); $table->softDeletes(); $table->index(['customer_id', 'type']);
        });
        Schema::create('customer_contacts', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('designation')->nullable(); $table->string('email')->nullable(); $table->string('phone')->nullable(); $table->string('whatsapp')->nullable(); $table->boolean('is_primary')->default(false); $table->text('notes')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('customer_activity_logs', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); $table->string('activity_type'); $table->string('title'); $table->text('description')->nullable(); $table->string('reference_type')->nullable(); $table->unsignedBigInteger('reference_id')->nullable(); $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('occurred_at'); $table->json('metadata')->nullable(); $table->timestamps(); $table->index(['customer_id', 'occurred_at']);
        });
        Schema::create('customer_loyalty_accounts', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); $table->string('loyalty_number'); $table->string('tier')->default('standard'); $table->integer('points_balance')->default(0); $table->integer('lifetime_points_earned')->default(0); $table->integer('lifetime_points_redeemed')->default(0); $table->timestamp('joined_at')->nullable(); $table->timestamp('last_activity_at')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['company_id', 'customer_id']); $table->unique(['company_id', 'loyalty_number']);
        });
        Schema::create('customer_loyalty_transactions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); $table->foreignId('loyalty_account_id')->constrained('customer_loyalty_accounts')->cascadeOnDelete(); $table->string('transaction_type'); $table->integer('points'); $table->string('reference_type')->nullable(); $table->unsignedBigInteger('reference_id')->nullable(); $table->text('description')->nullable(); $table->timestamp('expires_at')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->json('metadata')->nullable(); $table->timestamps(); $table->index(['customer_id', 'created_at']);
        });
        Schema::create('customer_wallet_transactions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); $table->string('transaction_type'); $table->decimal('amount', 14, 2); $table->decimal('balance_after', 14, 2); $table->string('reference_type')->nullable(); $table->unsignedBigInteger('reference_id')->nullable(); $table->text('description')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->json('metadata')->nullable(); $table->timestamps(); $table->index(['customer_id', 'created_at']);
        });
        Schema::create('customer_return_summaries', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('return_count')->default(0); $table->decimal('return_amount', 14, 2)->default(0); $table->decimal('return_quantity', 14, 3)->default(0); $table->decimal('frequent_return_score', 8, 2)->nullable(); $table->timestamp('last_return_at')->nullable(); $table->json('reason_summary')->nullable(); $table->boolean('is_frequent_returner')->default(false); $table->timestamp('calculated_at'); $table->timestamps(); $table->unique(['company_id', 'customer_id']);
        });
        Schema::create('customer_insight_snapshots', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); $table->decimal('total_purchase_amount', 14, 2)->default(0); $table->unsignedInteger('total_orders_count')->default(0); $table->decimal('average_order_value', 14, 2)->nullable(); $table->timestamp('last_purchase_at')->nullable(); $table->unsignedInteger('days_since_last_purchase')->nullable(); $table->decimal('total_return_amount', 14, 2)->default(0); $table->unsignedInteger('total_returns_count')->default(0); $table->decimal('return_rate', 8, 3)->nullable(); $table->integer('loyalty_points_balance')->default(0); $table->decimal('customer_value_score', 8, 2)->nullable(); $table->decimal('retention_risk_score', 8, 2)->nullable(); $table->decimal('return_risk_score', 8, 2)->nullable(); $table->string('segment_label')->nullable(); $table->boolean('is_top_customer')->default(false); $table->boolean('is_inactive_90_days')->default(false); $table->boolean('is_lost_customer')->default(false); $table->boolean('is_frequent_returner')->default(false); $table->timestamp('calculated_at'); $table->text('notes')->nullable(); $table->timestamps(); $table->unique(['company_id', 'customer_id']); $table->index(['company_id', 'is_top_customer', 'is_lost_customer']);
        });
        Schema::create('customer_settings', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->string('customer_number_prefix')->default('CUS'); $table->unsignedInteger('next_customer_number')->default(1); $table->foreignId('default_customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete(); $table->unsignedInteger('birthday_reminder_days_before')->default(7); $table->unsignedInteger('inactive_customer_days')->default(90); $table->unsignedInteger('lost_customer_days')->default(180); $table->unsignedInteger('frequent_return_threshold_count')->default(3); $table->unsignedInteger('frequent_return_threshold_days')->default(90); $table->boolean('loyalty_enabled')->default(true); $table->boolean('wallet_enabled')->default(true); $table->decimal('loyalty_points_per_amount', 14, 2)->nullable(); $table->decimal('loyalty_amount_per_point', 14, 2)->nullable(); $table->boolean('allow_negative_wallet')->default(false); $table->timestamps(); $table->unique('company_id');
        });
    }
    public function down(): void
    {
        foreach (['customer_settings','customer_insight_snapshots','customer_return_summaries','customer_wallet_transactions','customer_loyalty_transactions','customer_loyalty_accounts','customer_activity_logs','customer_contacts','customer_addresses','customer_group_members','customer_groups','customers'] as $table) Schema::dropIfExists($table);
    }
};
