<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table): void {
            $table->decimal('overall_discount_total', 14, 2)->default(0)->after('discount_total');
        });
        Schema::table('crm_invoice_amendments', function (Blueprint $table): void {
            $table->string('amendment_type', 32)->default('addition')->after('invoice_id');
            $table->string('discount_type', 16)->nullable()->after('reason');
            $table->decimal('discount_value', 14, 3)->nullable()->after('discount_type');
        });
        Schema::create('crm_invoice_amendment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amendment_id')->constrained('crm_invoice_amendments')->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->constrained('crm_invoice_items')->restrictOnDelete();
            $table->decimal('taxable_discount', 14, 2);
            $table->decimal('tax_reduction', 14, 2)->default(0);
            $table->decimal('cgst_reduction', 14, 2)->default(0);
            $table->decimal('sgst_reduction', 14, 2)->default(0);
            $table->decimal('igst_reduction', 14, 2)->default(0);
            $table->decimal('cess_reduction', 14, 2)->default(0);
            $table->decimal('total_reduction', 14, 2);
            $table->timestamps();
            $table->unique(['amendment_id', 'invoice_item_id'], 'crm_inv_amend_alloc_line_uq');
            $table->index(['invoice_item_id', 'amendment_id'], 'crm_inv_amend_alloc_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_invoice_amendment_allocations');
        Schema::table('crm_invoice_amendments', function (Blueprint $table): void { $table->dropColumn(['amendment_type', 'discount_type', 'discount_value']); });
        Schema::table('crm_invoices', function (Blueprint $table): void { $table->dropColumn('overall_discount_total'); });
    }
};
