<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_customers', function (Blueprint $table): void {
            $table->decimal('credit_limit', 14, 2)->nullable()->after('tax_number');
            $table->unsignedSmallInteger('credit_terms_days')->nullable()->after('credit_limit');
            $table->index(['company_id', 'credit_limit'], 'crm_customer_company_credit_limit_idx');
        });

        Schema::table('crm_invoice_payments', function (Blueprint $table): void {
            $table->dropForeign(['invoice_id']);
        });
        Schema::table('crm_invoice_payments', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->change();
            $table->foreignId('customer_id')->nullable()->after('invoice_id')->constrained('crm_customers')->nullOnDelete();
            $table->decimal('allocated_amount', 14, 2)->default(0)->after('amount');
            $table->decimal('unallocated_amount', 14, 2)->default(0)->after('allocated_amount');
            $table->index(['company_id', 'customer_id', 'payment_date'], 'crm_payment_company_customer_date_idx');
            $table->index(['company_id', 'unallocated_amount', 'status'], 'crm_payment_company_unalloc_status_idx');
            $table->foreign('invoice_id', 'crm_invoice_payments_invoice_id_foreign')->references('id')->on('crm_invoices')->cascadeOnDelete();
        });

        Schema::create('crm_invoice_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->constrained('crm_invoice_payments')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('crm_invoices')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('idempotency_key', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payment_id', 'invoice_id'], 'crm_pay_alloc_payment_invoice_idx');
            $table->unique(['company_id', 'idempotency_key'], 'crm_pay_alloc_company_idem_uq');
            $table->index(['company_id', 'invoice_id'], 'crm_pay_alloc_company_invoice_idx');
        });

        Schema::create('crm_customer_credit_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained('crm_customers')->restrictOnDelete();
            $table->foreignId('crm_invoice_return_id')->constrained('crm_invoice_returns')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('crm_invoices')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('idempotency_key', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key'], 'crm_credit_alloc_company_idem_uq');
            $table->index(['company_id', 'customer_id', 'created_at'], 'crm_credit_alloc_company_customer_idx');
            $table->index(['crm_invoice_return_id', 'invoice_id'], 'crm_credit_alloc_return_invoice_idx');
        });

        Schema::create('finance_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_type', 40);
            $table->unsignedBigInteger('payment_id');
            $table->string('status', 24)->default('reviewed');
            $table->text('note')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'payment_type', 'payment_id'], 'finance_recon_company_payment_uq');
            $table->index(['company_id', 'status', 'created_at'], 'finance_recon_company_status_idx');
        });

        DB::table('crm_invoice_payments')->orderBy('id')->eachById(function (object $payment): void {
            $invoice = DB::table('crm_invoices')->where('id', $payment->invoice_id)->first(['customer_id']);
            $active = ! in_array((string) $payment->status, ['failed', 'reversed'], true);
            DB::table('crm_invoice_payments')->where('id', $payment->id)->update([
                'customer_id' => $invoice?->customer_id,
                'allocated_amount' => $active ? $payment->amount : 0,
                'unallocated_amount' => 0,
            ]);
            if ($active) {
                DB::table('crm_invoice_payment_allocations')->updateOrInsert(
                    ['payment_id' => $payment->id, 'invoice_id' => $payment->invoice_id],
                    ['company_id' => $payment->company_id, 'branch_id' => $payment->branch_id, 'amount' => $payment->amount, 'created_by' => $payment->recorded_by, 'created_at' => $payment->created_at, 'updated_at' => $payment->updated_at],
                );
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_reconciliations');
        Schema::dropIfExists('crm_customer_credit_allocations');
        Schema::dropIfExists('crm_invoice_payment_allocations');

        Schema::table('crm_invoice_payments', function (Blueprint $table): void {
            $table->dropIndex('crm_payment_company_customer_date_idx');
            $table->dropIndex('crm_payment_company_unalloc_status_idx');
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'allocated_amount', 'unallocated_amount']);
            $table->dropForeign(['invoice_id']);
        });
        Schema::table('crm_invoice_payments', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable(false)->change();
            $table->foreign('invoice_id', 'crm_invoice_payments_invoice_id_foreign')->references('id')->on('crm_invoices')->cascadeOnDelete();
        });

        Schema::table('crm_customers', function (Blueprint $table): void {
            $table->dropIndex('crm_customer_company_credit_limit_idx');
            $table->dropColumn(['credit_limit', 'credit_terms_days']);
        });
    }
};
