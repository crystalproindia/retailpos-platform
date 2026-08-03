<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_billing_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('require_open_session')->default(true);
            $table->boolean('tax_inclusive_pricing')->default(false);
            $table->string('tax_rounding_mode', 24)->default('half_up');
            $table->timestamps();

            $table->unique('company_id', 'pos_billing_settings_company_uq');
        });

        Schema::create('pos_invoice_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('financial_year', 9);
            $table->string('prefix', 24);
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'branch_id', 'financial_year', 'prefix'], 'pos_inv_seq_company_branch_fy_prefix_uq');
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->string('financial_year', 9)->nullable()->after('receipt_number');
            $table->string('timezone', 64)->nullable()->after('financial_year');
            $table->string('place_of_supply_state_code', 2)->nullable()->after('timezone');
            $table->string('tax_treatment_snapshot', 24)->nullable()->after('place_of_supply_state_code');
            $table->decimal('taxable_amount', 14, 2)->default(0)->after('bill_discount_total');
            $table->decimal('cgst_total', 14, 2)->default(0)->after('taxable_amount');
            $table->decimal('sgst_total', 14, 2)->default(0)->after('cgst_total');
            $table->decimal('igst_total', 14, 2)->default(0)->after('sgst_total');
            $table->decimal('cess_total', 14, 2)->default(0)->after('igst_total');
            $table->index(['company_id', 'branch_id', 'financial_year'], 'pos_sale_company_branch_fy_idx');
        });

        Schema::table('pos_sale_items', function (Blueprint $table): void {
            $table->decimal('gross_amount', 14, 2)->default(0)->after('unit_price');
            $table->decimal('cgst_amount', 14, 2)->default(0)->after('tax_amount');
            $table->decimal('sgst_amount', 14, 2)->default(0)->after('cgst_amount');
            $table->decimal('igst_amount', 14, 2)->default(0)->after('sgst_amount');
            $table->decimal('cess_amount', 14, 2)->default(0)->after('igst_amount');
            $table->string('tax_treatment_snapshot', 24)->nullable()->after('cess_amount');
        });

        Schema::table('pos_payments', function (Blueprint $table): void {
            $table->json('metadata')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('pos_payments', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });

        Schema::table('pos_sale_items', function (Blueprint $table): void {
            $table->dropColumn(['gross_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'cess_amount', 'tax_treatment_snapshot']);
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropIndex('pos_sale_company_branch_fy_idx');
            $table->dropColumn(['financial_year', 'timezone', 'place_of_supply_state_code', 'tax_treatment_snapshot', 'taxable_amount', 'cgst_total', 'sgst_total', 'igst_total', 'cess_total']);
        });

        Schema::dropIfExists('pos_invoice_sequences');
        Schema::dropIfExists('pos_billing_settings');
    }
};
