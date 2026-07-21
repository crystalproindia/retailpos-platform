<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_settings', function (Blueprint $table): void {
            $table->string('invoice_prefix', 16)->default('PINV')->after('return_prefix');
            $table->string('payment_prefix', 16)->default('SPAY')->after('invoice_prefix');
            $table->unsignedBigInteger('next_invoice_number')->default(1)->after('next_return_number');
            $table->unsignedBigInteger('next_payment_number')->default(1)->after('next_invoice_number');
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->string('idempotency_key', 80)->nullable()->after('invoice_number');
            $table->timestamp('verified_at')->nullable()->after('approved_at');
            $table->foreignId('verified_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('verified_at');
            $table->foreignId('cancelled_by')->nullable()->after('verified_by')->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 1000)->nullable()->after('cancelled_at');
            $table->unique(['company_id', 'idempotency_key'], 'purch_inv_company_idempotency_uq');
        });

        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->string('idempotency_key', 80)->nullable()->after('payment_number');
            $table->string('currency', 3)->default('INR')->after('payment_date');
            $table->string('cheque_number', 80)->nullable()->after('reference');
            $table->date('cheque_date')->nullable()->after('cheque_number');
            $table->string('attachment_path')->nullable()->after('notes');
            $table->foreignId('approved_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->unique(['company_id', 'idempotency_key'], 'supp_payment_company_idempotency_uq');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->dropUnique('supp_payment_company_idempotency_uq');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['idempotency_key', 'currency', 'cheque_number', 'cheque_date', 'attachment_path', 'approved_at']);
        });
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->dropUnique('purch_inv_company_idempotency_uq');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['idempotency_key', 'verified_at', 'cancelled_at', 'cancellation_reason']);
        });
        Schema::table('purchase_settings', function (Blueprint $table): void {
            $table->dropColumn(['invoice_prefix', 'payment_prefix', 'next_invoice_number', 'next_payment_number']);
        });
    }
};
