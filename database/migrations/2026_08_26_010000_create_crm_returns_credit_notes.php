<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_return_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('financial_year', 9);
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'financial_year'], 'crm_ret_seq_scope_uq');
        });

        Schema::create('crm_invoice_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('crm_invoices')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('crm_customers')->nullOnDelete();
            $table->string('credit_note_number', 64);
            $table->string('financial_year', 9);
            $table->date('issue_date');
            $table->string('status', 24)->default('finalized');
            $table->string('currency', 3)->default('INR');
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('taxable_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('cgst_total', 14, 2)->default(0);
            $table->decimal('sgst_total', 14, 2)->default(0);
            $table->decimal('igst_total', 14, 2)->default(0);
            $table->decimal('cess_total', 14, 2)->default(0);
            $table->decimal('credit_total', 14, 2)->default(0);
            $table->decimal('receivable_credit_applied', 14, 2)->default(0);
            $table->decimal('customer_credit_due', 14, 2)->default(0);
            $table->decimal('known_cogs_reversal', 14, 2)->default(0);
            $table->decimal('known_profit_reversal', 14, 2)->default(0);
            $table->unsignedInteger('unavailable_cost_item_count')->default(0);
            $table->string('reason_code', 32);
            $table->text('reason_note')->nullable();
            $table->string('idempotency_key', 64);
            $table->string('company_name_snapshot');
            $table->text('company_address_snapshot')->nullable();
            $table->string('company_tax_number_snapshot', 32)->nullable();
            $table->string('customer_name_snapshot')->nullable();
            $table->string('customer_company_snapshot')->nullable();
            $table->text('customer_address_snapshot')->nullable();
            $table->string('customer_tax_number_snapshot', 32)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at');
            $table->timestamps();

            $table->unique(['company_id', 'credit_note_number'], 'crm_ret_company_credit_uq');
            $table->unique(['company_id', 'idempotency_key'], 'crm_ret_company_idem_uq');
            $table->index(['company_id', 'branch_id', 'status', 'issue_date'], 'crm_ret_scope_status_date_idx');
            $table->index(['invoice_id', 'status'], 'crm_ret_invoice_status_idx');
            $table->index(['customer_id', 'finalized_at'], 'crm_ret_customer_final_idx');
        });

        Schema::create('crm_invoice_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crm_invoice_return_id')->constrained('crm_invoice_returns')->cascadeOnDelete();
            $table->foreignId('original_invoice_item_id')->constrained('crm_invoice_items')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name_snapshot');
            $table->string('sku_snapshot')->nullable();
            $table->string('hsn_sac_snapshot')->nullable();
            $table->string('unit_snapshot', 32)->nullable();
            $table->decimal('original_quantity', 14, 3);
            $table->decimal('previously_returned_quantity', 14, 3)->default(0);
            $table->decimal('return_quantity', 14, 3);
            $table->decimal('unit_price_snapshot', 14, 2);
            $table->decimal('gross_reversal', 14, 2)->default(0);
            $table->decimal('discount_reversal', 14, 2)->default(0);
            $table->decimal('taxable_reversal', 14, 2)->default(0);
            $table->decimal('tax_reversal', 14, 2)->default(0);
            $table->decimal('cgst_reversal', 14, 2)->default(0);
            $table->decimal('sgst_reversal', 14, 2)->default(0);
            $table->decimal('igst_reversal', 14, 2)->default(0);
            $table->decimal('cess_reversal', 14, 2)->default(0);
            $table->decimal('credit_total', 14, 2)->default(0);
            $table->string('cost_status', 24)->default('unavailable');
            $table->decimal('unit_cost_snapshot', 14, 2)->nullable();
            $table->decimal('cogs_reversal', 14, 2)->nullable();
            $table->decimal('gross_profit_reversal', 14, 2)->nullable();
            $table->boolean('restock_requested')->default(false);
            $table->string('inventory_disposition', 24)->default('not_restocked');
            $table->text('condition_note')->nullable();
            $table->timestamps();

            $table->index(['original_invoice_item_id', 'crm_invoice_return_id'], 'crm_ret_item_invoice_line_idx');
            $table->index(['product_id', 'inventory_disposition'], 'crm_ret_item_product_disp_idx');
        });

        Schema::table('crm_invoices', function (Blueprint $table): void {
            $table->decimal('credited_total', 14, 2)->default(0)->after('amount_paid');
            $table->string('return_status', 16)->default('none')->after('credited_total');
            $table->index(['company_id', 'branch_id', 'return_status'], 'crm_invoice_return_status_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('crm_invoice_return_item_id')->nullable()->after('pos_return_item_id')->constrained('crm_invoice_return_items')->nullOnDelete();
            $table->unique('crm_invoice_return_item_id', 'stock_move_crm_ret_item_uq');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['crm_invoice_return_item_id']);
            $table->dropUnique('stock_move_crm_ret_item_uq');
            $table->dropColumn('crm_invoice_return_item_id');
        });
        Schema::table('crm_invoices', function (Blueprint $table): void {
            $table->dropIndex('crm_invoice_return_status_idx');
            $table->dropColumn(['credited_total', 'return_status']);
        });
        Schema::dropIfExists('crm_invoice_return_items');
        Schema::dropIfExists('crm_invoice_returns');
        Schema::dropIfExists('crm_return_sequences');
    }
};
