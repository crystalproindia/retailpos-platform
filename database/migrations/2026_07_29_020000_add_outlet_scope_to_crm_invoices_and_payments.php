<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
            $table->index(['company_id', 'branch_id', 'created_at'], 'crm_invoice_company_branch_created_idx');
        });

        Schema::table('crm_invoice_payments', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
            $table->index(['company_id', 'branch_id', 'payment_date'], 'crm_inv_payment_company_branch_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crm_invoice_payments', function (Blueprint $table): void {
            $table->dropIndex('crm_inv_payment_company_branch_date_idx');
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::table('crm_invoices', function (Blueprint $table): void {
            $table->dropIndex('crm_invoice_company_branch_created_idx');
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
