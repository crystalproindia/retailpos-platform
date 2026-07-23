<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saas_subscription_invoices')) {
            Schema::create('saas_subscription_invoices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->foreignId('saas_subscription_id');
                $table->foreignId('saas_plan_id')->nullable();
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
                $table->foreignId('created_by')->nullable();
                $table->foreignId('issued_by')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->foreignId('voided_by')->nullable();
                $table->string('void_reason', 1000)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('saas_subscription_invoice_items')) {
            Schema::create('saas_subscription_invoice_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('saas_subscription_invoice_id');
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
            });
        }

        if (! Schema::hasTable('saas_billing_payments')) {
            Schema::create('saas_billing_payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->foreignId('saas_subscription_invoice_id');
                $table->foreignId('saas_subscription_id');
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
                $table->foreignId('recorded_by')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('reversed_by')->nullable();
                $table->string('reversal_reason', 1000)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('saas_billing_refunds')) {
            Schema::create('saas_billing_refunds', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->foreignId('saas_billing_payment_id');
                $table->foreignId('saas_subscription_invoice_id');
                $table->string('refund_number', 64);
                $table->string('provider', 80)->default('manual');
                $table->string('provider_refund_id', 160)->nullable();
                $table->string('status', 24)->default('requested');
                $table->decimal('amount', 14, 2);
                $table->string('currency', 3)->default('INR');
                $table->string('reason', 1000)->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('requested_by')->nullable();
                $table->foreignId('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        $this->ensureForeign('saas_subscription_invoices', 'company_id', 'companies', 'saas_inv_company_fk', 'cascade');
        $this->ensureForeign('saas_subscription_invoices', 'saas_subscription_id', 'saas_subscriptions', 'saas_inv_subscription_fk', 'cascade');
        $this->ensureForeign('saas_subscription_invoices', 'saas_plan_id', 'saas_plans', 'saas_inv_plan_fk', 'null');
        $this->ensureForeign('saas_subscription_invoices', 'created_by', 'users', 'saas_inv_created_by_fk', 'null');
        $this->ensureForeign('saas_subscription_invoices', 'issued_by', 'users', 'saas_inv_issued_by_fk', 'null');
        $this->ensureForeign('saas_subscription_invoices', 'voided_by', 'users', 'saas_inv_voided_by_fk', 'null');
        $this->ensureUnique('saas_subscription_invoices', ['company_id', 'invoice_number'], 'saas_bill_company_invoice_uq');
        $this->ensureUnique('saas_subscription_invoices', ['company_id', 'idempotency_key'], 'saas_bill_company_idem_uq');
        $this->ensureUnique('saas_subscription_invoices', ['company_id', 'saas_subscription_id', 'billing_period_starts_at', 'billing_period_ends_at', 'invoice_type'], 'saas_bill_subscription_period_uq');
        $this->ensureIndex('saas_subscription_invoices', ['company_id', 'status', 'due_date'], 'saas_bill_company_due_idx');
        $this->ensureIndex('saas_subscription_invoices', ['saas_subscription_id', 'status'], 'saas_bill_subscription_status_idx');
        $this->ensureIndex('saas_subscription_invoices', ['company_id', 'payment_status'], 'saas_bill_company_payment_idx');

        $this->ensureForeign('saas_subscription_invoice_items', 'saas_subscription_invoice_id', 'saas_subscription_invoices', 'saas_inv_items_invoice_fk', 'cascade');
        $this->ensureIndex('saas_subscription_invoice_items', ['saas_subscription_invoice_id', 'sort_order'], 'saas_bill_item_invoice_sort_idx');

        $this->ensureForeign('saas_billing_payments', 'company_id', 'companies', 'saas_pay_company_fk', 'cascade');
        $this->ensureForeign('saas_billing_payments', 'saas_subscription_invoice_id', 'saas_subscription_invoices', 'saas_pay_invoice_fk', 'restrict');
        $this->ensureForeign('saas_billing_payments', 'saas_subscription_id', 'saas_subscriptions', 'saas_pay_subscription_fk', 'restrict');
        $this->ensureForeign('saas_billing_payments', 'recorded_by', 'users', 'saas_pay_recorded_by_fk', 'null');
        $this->ensureForeign('saas_billing_payments', 'reversed_by', 'users', 'saas_pay_reversed_by_fk', 'null');
        $this->ensureUnique('saas_billing_payments', ['company_id', 'payment_number'], 'saas_pay_company_number_uq');
        $this->ensureUnique('saas_billing_payments', ['receipt_number'], 'saas_pay_receipt_uq');
        $this->ensureUnique('saas_billing_payments', ['company_id', 'idempotency_key'], 'saas_pay_company_idem_uq');
        $this->ensureUnique('saas_billing_payments', ['provider', 'provider_payment_id'], 'saas_pay_provider_payment_uq');
        $this->ensureIndex('saas_billing_payments', ['saas_subscription_invoice_id', 'status'], 'saas_pay_invoice_status_idx');
        $this->ensureIndex('saas_billing_payments', ['company_id', 'reconciliation_status'], 'saas_pay_company_recon_idx');

        $this->ensureForeign('saas_billing_refunds', 'company_id', 'companies', 'saas_ref_company_fk', 'cascade');
        $this->ensureForeign('saas_billing_refunds', 'saas_billing_payment_id', 'saas_billing_payments', 'saas_ref_payment_fk', 'restrict');
        $this->ensureForeign('saas_billing_refunds', 'saas_subscription_invoice_id', 'saas_subscription_invoices', 'saas_ref_invoice_fk', 'restrict');
        $this->ensureForeign('saas_billing_refunds', 'requested_by', 'users', 'saas_ref_requested_by_fk', 'null');
        $this->ensureForeign('saas_billing_refunds', 'approved_by', 'users', 'saas_ref_approved_by_fk', 'null');
        $this->ensureUnique('saas_billing_refunds', ['company_id', 'refund_number'], 'saas_ref_company_number_uq');
        $this->ensureUnique('saas_billing_refunds', ['provider', 'provider_refund_id'], 'saas_ref_provider_refund_uq');
        $this->ensureIndex('saas_billing_refunds', ['saas_subscription_invoice_id', 'status'], 'saas_ref_invoice_status_idx');
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_refunds');
        Schema::dropIfExists('saas_billing_payments');
        Schema::dropIfExists('saas_subscription_invoice_items');
        Schema::dropIfExists('saas_subscription_invoices');
    }

    /** @param list<string> $columns */
    private function ensureIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasIndex($table, $columns)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
        }
    }

    /** @param list<string> $columns */
    private function ensureUnique(string $table, array $columns, string $name): void
    {
        if (! Schema::hasIndex($table, $columns, 'unique')) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
        }
    }

    private function ensureForeign(string $table, string $column, string $referenceTable, string $name, string $onDelete): void
    {
        if (Schema::hasForeignKey($table, [$column])) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referenceTable, $name, $onDelete): void {
            $foreign = $blueprint->foreign($column, $name)->references('id')->on($referenceTable);
            match ($onDelete) {
                'cascade' => $foreign->cascadeOnDelete(),
                'null' => $foreign->nullOnDelete(),
                default => $foreign->restrictOnDelete(),
            };
        });
    }
};
