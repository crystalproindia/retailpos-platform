<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('registration_type', 24)->default('unregistered');
            $table->text('registered_address')->nullable();
            $table->string('state_code', 2)->nullable();
            $table->string('state_name', 80)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('default_place_of_supply_state_code', 2)->nullable();
            $table->string('invoice_series', 40)->default('RPOS-INV');
            $table->string('financial_year', 9)->nullable();
            $table->boolean('e_invoice_applicable')->default(false);
            $table->boolean('e_way_bill_applicable')->default(false);
            $table->string('aggregate_turnover_band', 48)->nullable();
            $table->string('tax_rounding_mode', 24)->default('half_up');
            $table->boolean('reverse_charge_default')->default(false);
            $table->string('export_type', 24)->default('domestic');
            $table->timestamp('accountant_reviewed_at')->nullable();
            $table->foreignId('accountant_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('company_id', 'gst_settings_company_uq');
        });

        Schema::create('gst_state_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 2)->unique('gst_state_code_uq');
            $table->string('name', 80);
            $table->boolean('is_union_territory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gst_classifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('classification_type', 8);
            $table->string('code', 16);
            $table->string('description')->nullable();
            $table->string('uqc', 16)->nullable();
            $table->decimal('default_gst_rate', 8, 3)->default(0);
            $table->decimal('cess_rate', 8, 3)->nullable();
            $table->string('exemption_reason', 255)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'classification_type', 'code'], 'gst_class_company_type_code_uq');
        });

        Schema::create('gst_document_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('note_number', 64);
            $table->string('note_type', 8);
            $table->string('original_document_type', 80);
            $table->unsignedBigInteger('original_document_id');
            $table->string('original_invoice_number', 80)->nullable();
            $table->string('customer_name_snapshot')->nullable();
            $table->string('customer_gstin_snapshot', 15)->nullable();
            $table->string('reason_code', 48)->nullable();
            $table->text('reason_description')->nullable();
            $table->date('issue_date');
            $table->string('financial_year', 9);
            $table->string('place_of_supply_state_code', 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('cgst_total', 14, 2)->default(0);
            $table->decimal('sgst_total', 14, 2)->default(0);
            $table->decimal('igst_total', 14, 2)->default(0);
            $table->decimal('cess_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->string('status', 16)->default('draft');
            $table->string('gst_reporting_period', 7)->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'note_number'], 'gst_note_company_number_uq');
            $table->index(['company_id', 'issue_date', 'status'], 'gst_note_company_date_status_idx');
            $table->index(['original_document_type', 'original_document_id'], 'gst_note_original_doc_idx');
        });

        Schema::create('gst_document_note_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gst_document_note_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('hsn_sac', 16)->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 32)->nullable();
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

        Schema::create('gst_return_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->string('financial_year', 9);
            $table->string('status', 24)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->string('reopen_reason', 1000)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'period'], 'gst_period_company_period_uq');
        });

        Schema::create('gst_export_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('export_type', 32);
            $table->string('period', 7)->nullable();
            $table->string('format', 8);
            $table->boolean('draft_export')->default(false);
            $table->unsignedInteger('record_count')->default(0);
            $table->json('validation_summary')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'created_at'], 'gst_export_company_created_idx');
        });

        Schema::table('crm_invoices', function (Blueprint $table): void {
            $table->string('supplier_gstin_snapshot', 15)->nullable()->after('invoice_number');
            $table->string('supplier_state_code_snapshot', 2)->nullable()->after('supplier_gstin_snapshot');
            $table->string('place_of_supply_state_code', 2)->nullable()->after('place_of_supply');
            $table->string('tax_treatment_snapshot', 24)->nullable()->after('tax_classification');
            $table->decimal('cgst_total', 14, 2)->default(0)->after('tax_total');
            $table->decimal('sgst_total', 14, 2)->default(0)->after('cgst_total');
            $table->decimal('igst_total', 14, 2)->default(0)->after('sgst_total');
            $table->decimal('cess_total', 14, 2)->default(0)->after('igst_total');
            $table->string('e_invoice_status', 24)->default('not_applicable');
            $table->string('e_way_bill_status', 24)->default('not_applicable');
            $table->string('safe_compliance_error', 1000)->nullable();
        });
        Schema::table('crm_invoice_items', function (Blueprint $table): void {
            $table->string('hsn_sac', 16)->nullable()->after('description');
            $table->string('tax_treatment_snapshot', 24)->nullable()->after('tax_rate');
            $table->decimal('cgst_amount', 14, 2)->default(0)->after('tax_amount');
            $table->decimal('sgst_amount', 14, 2)->default(0)->after('cgst_amount');
            $table->decimal('igst_amount', 14, 2)->default(0)->after('sgst_amount');
            $table->decimal('cess_amount', 14, 2)->default(0)->after('igst_amount');
        });
    }

    public function down(): void
    {
        Schema::table('crm_invoice_items', function (Blueprint $table): void {
            $table->dropColumn(['hsn_sac', 'tax_treatment_snapshot', 'cgst_amount', 'sgst_amount', 'igst_amount', 'cess_amount']);
        });
        Schema::table('crm_invoices', function (Blueprint $table): void {
            $table->dropColumn(['supplier_gstin_snapshot', 'supplier_state_code_snapshot', 'place_of_supply_state_code', 'tax_treatment_snapshot', 'cgst_total', 'sgst_total', 'igst_total', 'cess_total', 'e_invoice_status', 'e_way_bill_status', 'safe_compliance_error']);
        });
        Schema::dropIfExists('gst_export_logs');
        Schema::dropIfExists('gst_return_periods');
        Schema::dropIfExists('gst_document_note_items');
        Schema::dropIfExists('gst_document_notes');
        Schema::dropIfExists('gst_classifications');
        Schema::dropIfExists('gst_state_codes');
        Schema::dropIfExists('gst_settings');
    }
};
