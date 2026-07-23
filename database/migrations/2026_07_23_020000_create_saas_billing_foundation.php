<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_subscription_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 64);
            $table->string('invoice_type', 24)->default('renewal');
            $table->string('status', 24)->default('draft');
            $table->string('payment_status', 24)->default('unpaid');
            $table->string('financial_year', 9);
            $table->date('billing_period_starts_at');
            $table->date('billing_period_ends_at');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('billing_name')->nullable();
            $table->string('billing_company')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone', 50)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_country', 120)->nullable();
            $table->string('customer_gstin', 15)->nullable();
            $table->string('supplier_gstin_snapshot', 15)->nullable();
            $table->string('supplier_state_code_snapshot', 2)->nullable();
            $table->string('place_of_supply_state_code', 2)->nullable();
            $table->string('tax_treatment_snapshot', 24)->default('unconfigured');
            $table->boolean('reverse_charge')->default(false);
            $table->json('plan_snapshot');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('adjustment_total', 14, 2)->default(0);
            $table->decimal('credit_total', 14, 2)->default(0);
            $table->decimal('taxable_total', 14, 2)->default(0);
            $table->decimal('cgst_total', 14, 2)->default(0);
            $table->decimal('sgst_total', 14, 2)->default(0);
            $table->decimal('igst_total', 14, 2)->default(0);
            $table->decimal('cess_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('amount_refunded', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->string('provider', 80)->nullable();
            $table->string('provider_reference', 160)->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_remarks')->nullable();
            $table->string('idempotency_key', 160)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 1000)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'invoice_number'], 'saas_bill_company_invoice_uq');
            $table->unique(['company_id', 'idempotency_key'], 'saas_bill_company_idem_uq');
            $table->unique(['company_id', 'saas_subscription_id', 'billing_period_starts_at', 'billing_period_ends_at', 'invoice_type'], 'saas_bill_subscription_period_uq');
            $table->index(['company_id', 'status', 'due_date'], 'saas_bill_company_due_idx');
            $table->index(['saas_subscription_id', 'status'], 'saas_bill_subscription_status_idx');
            $table->index(['company_id', 'payment_status'], 'saas_bill_company_payment_idx');
        });

        Schema::create('saas_subscription_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saas_subscription_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('line_type', 32);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('hsn_sac', 16)->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 32)->default('service');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('adjustment_amount', 14, 2)->default(0);
            $table->decimal('credit_amount', 14, 2)->default(0);
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('tax_rate', 8, 3)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('cess_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['saas_subscription_invoice_id', 'sort_order'], 'saas_bill_item_invoice_sort_idx');
        });

        Schema::create('saas_billing_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_subscription_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('saas_subscription_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 64);
            $table->string('receipt_number', 64)->nullable();
            $table->string('provider', 80)->default('manual');
            $table->string('provider_payment_id', 160)->nullable();
            $table->string('provider_order_id', 160)->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('payment_method', 32)->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('refund_total', 14, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('transaction_reference', 160)->nullable();
            $table->string('bank_name', 160)->nullable();
            $table->string('cheque_number', 100)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->string('failure_message', 1000)->nullable();
            $table->string('reconciliation_status', 24)->default('unreconciled');
            $table->json('metadata')->nullable();
            $table->string('idempotency_key', 160)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 1000)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'payment_number'], 'saas_pay_company_number_uq');
            $table->unique('receipt_number', 'saas_pay_receipt_uq');
            $table->unique(['company_id', 'idempotency_key'], 'saas_pay_company_idem_uq');
            $table->unique(['provider', 'provider_payment_id'], 'saas_pay_provider_payment_uq');
            $table->index(['saas_subscription_invoice_id', 'status'], 'saas_pay_invoice_status_idx');
            $table->index(['company_id', 'reconciliation_status'], 'saas_pay_company_recon_idx');
        });

        Schema::create('saas_billing_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_billing_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('saas_subscription_invoice_id')->constrained()->restrictOnDelete();
            $table->string('refund_number', 64);
            $table->string('provider', 80)->default('manual');
            $table->string('provider_refund_id', 160)->nullable();
            $table->string('status', 24)->default('requested');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('reason', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'refund_number'], 'saas_ref_company_number_uq');
            $table->unique(['provider', 'provider_refund_id'], 'saas_ref_provider_refund_uq');
            $table->index(['saas_subscription_invoice_id', 'status'], 'saas_ref_invoice_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_refunds');
        Schema::dropIfExists('saas_billing_payments');
        Schema::dropIfExists('saas_subscription_invoice_items');
        Schema::dropIfExists('saas_subscription_invoices');
    }
};
