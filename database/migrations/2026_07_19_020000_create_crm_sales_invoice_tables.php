<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('crm_quotations')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('crm_opportunities')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('crm_customers')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('invoice_number', 40);
            $table->string('billing_name')->nullable();
            $table->string('billing_company')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone', 50)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_country', 120)->nullable();
            $table->string('customer_tax_number', 120)->nullable();
            $table->string('place_of_supply', 120)->nullable();
            $table->string('tax_classification', 24)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('taxable_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('adjustment_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->string('status', 24)->default('draft');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->longText('notes')->nullable();
            $table->longText('terms_conditions')->nullable();
            $table->longText('internal_notes')->nullable();
            $table->string('public_token_hash', 64)->nullable()->unique('crm_invoice_public_hash_uq');
            $table->timestamp('public_token_expires_at')->nullable();
            $table->timestamp('public_token_revoked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('public_view_count')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->date('do_not_remind_before')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'invoice_number'], 'crm_invoice_company_number_uq');
            $table->index(['company_id', 'status'], 'crm_invoice_company_status_idx');
            $table->index(['company_id', 'due_date'], 'crm_invoice_company_due_idx');
            $table->index('quotation_id', 'crm_invoice_quote_idx');
            $table->index('opportunity_id', 'crm_invoice_opp_idx');
            $table->index('customer_id', 'crm_invoice_customer_idx');
        });

        Schema::create('crm_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('crm_invoices')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 32)->default('unit');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->string('discount_type', 16)->default('fixed');
            $table->decimal('discount_value', 14, 3)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 8, 3)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['invoice_id', 'sort_order'], 'crm_invoice_item_sort_idx');
        });

        Schema::create('crm_invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('crm_invoices')->cascadeOnDelete();
            $table->string('payment_reference', 64);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->date('payment_date');
            $table->string('payment_method', 32);
            $table->string('transaction_reference', 160)->nullable();
            $table->string('bank_name', 160)->nullable();
            $table->string('cheque_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('recorded');
            $table->string('receipt_number', 40)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 1000)->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'payment_reference'], 'crm_inv_payment_company_ref_uq');
            $table->unique(['company_id', 'idempotency_key'], 'crm_inv_payment_company_idem_uq');
            $table->unique('receipt_number', 'crm_inv_payment_receipt_uq');
            $table->index(['invoice_id', 'status'], 'crm_inv_payment_invoice_status_idx');
            $table->index(['company_id', 'transaction_reference'], 'crm_inv_payment_company_txn_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_invoice_payments');
        Schema::dropIfExists('crm_invoice_items');
        Schema::dropIfExists('crm_invoices');
    }
};
