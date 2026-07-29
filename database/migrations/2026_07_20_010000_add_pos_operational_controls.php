<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('store_type', 32)->default('retail_store')->after('code');
            $table->string('postal_code', 32)->nullable()->after('state');
            $table->string('country_code', 2)->default('IN')->after('country');
            $table->string('tax_number', 120)->nullable()->after('country_code');
            $table->string('timezone', 64)->nullable()->after('tax_number');
            $table->string('currency', 3)->nullable()->after('timezone');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
        });

        Schema::create('pos_registers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 48);
            $table->string('name');
            $table->string('receipt_prefix', 24)->default('POS');
            $table->foreignId('current_session_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'code'], 'pos_register_branch_code_uq');
            $table->index(['company_id', 'branch_id', 'is_active'], 'pos_register_company_branch_active_idx');
        });

        Schema::create('pos_register_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->constrained('pos_registers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('closing_cash', 14, 2)->nullable();
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('variance', 14, 2)->nullable();
            $table->string('status', 16)->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'branch_id', 'status'], 'pos_session_company_branch_status_idx');
            $table->index(['register_id', 'status'], 'pos_session_register_status_idx');
        });

        Schema::table('pos_registers', function (Blueprint $table): void {
            $table->foreign('current_session_id', 'pos_register_current_session_fk')->references('id')->on('pos_register_sessions')->nullOnDelete();
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->foreignId('register_id')->nullable()->after('branch_id')->constrained('pos_registers')->nullOnDelete();
            $table->foreignId('register_session_id')->nullable()->after('register_id')->constrained('pos_register_sessions')->nullOnDelete();
            $table->string('receipt_number', 64)->nullable()->after('sale_number');
            $table->string('customer_name_snapshot')->nullable()->after('customer_id');
            $table->string('customer_mobile_snapshot', 48)->nullable()->after('customer_name_snapshot');
            $table->string('currency', 3)->default('INR')->after('status');
            $table->string('sale_type', 16)->default('retail')->after('currency');
            $table->decimal('item_discount_total', 14, 2)->default(0)->after('discount_amount');
            $table->decimal('bill_discount_total', 14, 2)->default(0)->after('item_discount_total');
            $table->decimal('rounding_adjustment', 14, 2)->default(0)->after('tax_amount');
            $table->decimal('balance_due', 14, 2)->default(0)->after('change_amount');
            $table->timestamp('sold_at')->nullable()->after('completed_at');
            $table->foreignId('voided_by')->nullable()->after('completed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('sold_at');
            $table->string('void_reason', 1000)->nullable()->after('voided_at');
            $table->string('completion_key', 64)->nullable()->after('offline_uuid');
            $table->unique(['company_id', 'receipt_number'], 'pos_sale_company_receipt_uq');
            $table->unique(['company_id', 'completion_key'], 'pos_sale_company_complete_key_uq');
            $table->index(['company_id', 'register_id', 'completed_at'], 'pos_sale_company_register_completed_idx');
        });

        Schema::table('pos_sale_items', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('products')->nullOnDelete();
            $table->string('variant_label')->nullable()->after('barcode');
            $table->string('hsn_sac', 80)->nullable()->after('variant_label');
            $table->string('unit', 32)->nullable()->after('hsn_sac');
            $table->string('price_source', 32)->default('product')->after('unit_price');
            $table->string('discount_type', 16)->default('fixed')->after('price_source');
            $table->decimal('discount_value', 14, 3)->default(0)->after('discount_type');
            $table->decimal('taxable_amount', 14, 2)->default(0)->after('discount_amount');
            $table->string('tax_profile_name')->nullable()->after('taxable_amount');
            $table->decimal('tax_rate', 8, 3)->default(0)->after('tax_profile_name');
            $table->json('tax_components')->nullable()->after('tax_rate');
            $table->unsignedInteger('sort_order')->default(0)->after('line_total');
        });

        Schema::table('pos_payments', function (Blueprint $table): void {
            $table->string('status', 16)->default('recorded')->after('reference');
            $table->foreignId('reversed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('pos_payments', function (Blueprint $table): void {
            $table->dropForeign(['reversed_by']);
            $table->dropColumn(['status', 'reversed_by', 'reversed_at']);
        });
        Schema::table('pos_sale_items', function (Blueprint $table): void {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn(['product_variant_id', 'variant_label', 'hsn_sac', 'unit', 'price_source', 'discount_type', 'discount_value', 'taxable_amount', 'tax_profile_name', 'tax_rate', 'tax_components', 'sort_order']);
        });
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropUnique('pos_sale_company_receipt_uq');
            $table->dropUnique('pos_sale_company_complete_key_uq');
            $table->dropIndex('pos_sale_company_register_completed_idx');
            $table->dropForeign(['register_id', 'register_session_id', 'voided_by']);
            $table->dropColumn(['register_id', 'register_session_id', 'receipt_number', 'customer_name_snapshot', 'customer_mobile_snapshot', 'currency', 'sale_type', 'item_discount_total', 'bill_discount_total', 'rounding_adjustment', 'balance_due', 'sold_at', 'voided_by', 'voided_at', 'void_reason', 'completion_key']);
        });
        Schema::table('pos_registers', function (Blueprint $table): void {
            $table->dropForeign('pos_register_current_session_fk');
        });
        Schema::dropIfExists('pos_register_sessions');
        Schema::dropIfExists('pos_registers');
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['legal_name', 'store_type', 'postal_code', 'country_code', 'tax_number', 'timezone', 'currency', 'created_by']);
        });
    }
};
