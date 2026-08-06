<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_return_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('return_window_days')->default(7);
            $table->boolean('receipt_required')->default(true);
            $table->boolean('manager_approval_required')->default(true);
            $table->boolean('cashiers_may_initiate')->default(true);
            $table->boolean('refund_original_method_only')->default(false);
            $table->boolean('store_credit_allowed')->default(true);
            $table->boolean('damaged_may_restock')->default(false);
            $table->boolean('anonymous_returns_allowed')->default(false);
            $table->decimal('approval_threshold', 14, 2)->nullable();
            $table->timestamps();
            $table->unique('company_id', 'pos_return_settings_company_unique');
        });

        Schema::create('pos_return_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('financial_year', 9);
            $table->unsignedInteger('last_return_sequence')->default(0);
            $table->unsignedInteger('last_credit_note_sequence')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'financial_year'], 'pos_return_sequence_scope_unique');
        });

        Schema::create('pos_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('original_sale_id')->constrained('pos_sales')->restrictOnDelete();
            $table->foreignId('exchange_sale_id')->nullable()->constrained('pos_sales')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('return_number');
            $table->string('credit_note_number')->nullable();
            $table->string('financial_year', 9);
            $table->string('return_type')->default('partial_return');
            $table->string('status')->default('draft');
            $table->date('return_date');
            $table->string('timezone', 64);
            $table->string('currency', 3)->default('INR');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_adjustment_total', 14, 2)->default(0);
            $table->decimal('taxable_adjustment_total', 14, 2)->default(0);
            $table->decimal('tax_adjustment_total', 14, 2)->default(0);
            $table->decimal('cgst_adjustment_total', 14, 2)->default(0);
            $table->decimal('sgst_adjustment_total', 14, 2)->default(0);
            $table->decimal('igst_adjustment_total', 14, 2)->default(0);
            $table->decimal('cess_adjustment_total', 14, 2)->default(0);
            $table->decimal('refund_total', 14, 2)->default(0);
            $table->decimal('store_credit_total', 14, 2)->default(0);
            $table->decimal('exchange_payable_total', 14, 2)->default(0);
            $table->decimal('exchange_refund_total', 14, 2)->default(0);
            $table->string('reason_code')->nullable();
            $table->text('reason_text')->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 100);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'pos_return_company_idem_unique');
            $table->unique(['company_id', 'branch_id', 'financial_year', 'return_number'], 'pos_return_number_scope_unique');
            $table->unique(['company_id', 'credit_note_number'], 'pos_return_credit_note_unique');
            $table->index(['company_id', 'branch_id', 'status', 'return_date'], 'pos_return_scope_status_date_idx');
            $table->index(['original_sale_id', 'status'], 'pos_return_sale_status_idx');
            $table->index(['customer_id', 'completed_at'], 'pos_return_customer_complete_idx');
        });

        Schema::create('pos_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_return_id')->constrained('pos_returns')->cascadeOnDelete();
            $table->foreignId('original_sale_item_id')->constrained('pos_sale_items')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('variant_label')->nullable();
            $table->string('hsn_sac')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('original_quantity', 14, 3);
            $table->decimal('previously_returned_quantity', 14, 3)->default(0);
            $table->decimal('return_quantity', 14, 3);
            $table->decimal('unit_price_snapshot', 14, 2);
            $table->decimal('gross_adjustment', 14, 2)->default(0);
            $table->decimal('discount_adjustment', 14, 2)->default(0);
            $table->decimal('taxable_adjustment', 14, 2)->default(0);
            $table->decimal('tax_adjustment', 14, 2)->default(0);
            $table->decimal('cgst_adjustment', 14, 2)->default(0);
            $table->decimal('sgst_adjustment', 14, 2)->default(0);
            $table->decimal('igst_adjustment', 14, 2)->default(0);
            $table->decimal('cess_adjustment', 14, 2)->default(0);
            $table->decimal('line_refund_total', 14, 2)->default(0);
            $table->string('stock_disposition')->default('restock');
            $table->text('condition_note')->nullable();
            $table->timestamps();
            $table->index(['original_sale_item_id', 'pos_return_id'], 'pos_return_item_sale_item_idx');
            $table->index(['product_id', 'stock_disposition'], 'pos_return_item_product_disp_idx');
        });

        Schema::create('pos_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_return_id')->constrained('pos_returns')->cascadeOnDelete();
            $table->foreignId('original_payment_id')->nullable()->constrained('pos_payments')->nullOnDelete();
            $table->string('method');
            $table->decimal('amount', 14, 2);
            $table->string('external_reference')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'method', 'status'], 'pos_refund_company_method_status_idx');
            $table->index(['pos_return_id', 'status'], 'pos_refund_return_status_idx');
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->decimal('returned_amount', 14, 2)->default(0)->after('balance_due');
            $table->string('return_status')->default('none')->after('returned_amount');
            $table->index(['company_id', 'branch_id', 'return_status'], 'pos_sale_return_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropIndex('pos_sale_return_status_idx');
            $table->dropColumn(['returned_amount', 'return_status']);
        });
        Schema::dropIfExists('pos_refunds');
        Schema::dropIfExists('pos_return_items');
        Schema::dropIfExists('pos_returns');
        Schema::dropIfExists('pos_return_sequences');
        Schema::dropIfExists('pos_return_settings');
    }
};
