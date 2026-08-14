<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_document_settings')) {
            Schema::create('sales_document_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('invoice_prefix', 24)->default('RPOS');
                $table->string('quotation_prefix', 24)->default('RPQ');
                $table->string('proforma_prefix', 24)->default('RPI');
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique('company_id', 'sales_document_settings_company_uq');
            });
        }

        if (! Schema::hasColumn('sales_document_settings', 'proforma_prefix')) {
            Schema::table('sales_document_settings', function (Blueprint $table): void {
                $table->string('proforma_prefix', 24)->default('RPI')->after('quotation_prefix');
            });
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'authorized_signature_path')) {
                $table->string('authorized_signature_path')->nullable()->after('invoice_logo_path');
            }
            if (! Schema::hasColumn('companies', 'authorized_signatory_name')) {
                $table->string('authorized_signatory_name')->nullable()->after('authorized_signature_path');
            }
            if (! Schema::hasColumn('companies', 'authorized_signatory_designation')) {
                $table->string('authorized_signatory_designation')->nullable()->after('authorized_signatory_name');
            }
        });

        Schema::table('crm_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_invoices', 'tax_mode')) {
                $table->string('tax_mode', 16)->default('gst')->after('tax_classification');
            }
            if (! Schema::hasColumn('crm_invoices', 'show_authorized_signature')) {
                $table->boolean('show_authorized_signature')->default(true)->after('terms_conditions');
            }
            if (! Schema::hasColumn('crm_invoices', 'signature_path_snapshot')) {
                $table->string('signature_path_snapshot')->nullable()->after('show_authorized_signature');
            }
            if (! Schema::hasColumn('crm_invoices', 'signatory_name_snapshot')) {
                $table->string('signatory_name_snapshot')->nullable()->after('signature_path_snapshot');
            }
            if (! Schema::hasColumn('crm_invoices', 'signatory_designation_snapshot')) {
                $table->string('signatory_designation_snapshot')->nullable()->after('signatory_name_snapshot');
            }
        });

        Schema::table('crm_quotations', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_quotations', 'tax_mode')) {
                $table->string('tax_mode', 16)->default('gst')->after('currency');
            }
            if (! Schema::hasColumn('crm_quotations', 'show_authorized_signature')) {
                $table->boolean('show_authorized_signature')->default(true)->after('terms_conditions');
            }
            if (! Schema::hasColumn('crm_quotations', 'signature_path_snapshot')) {
                $table->string('signature_path_snapshot')->nullable()->after('show_authorized_signature');
            }
            if (! Schema::hasColumn('crm_quotations', 'signatory_name_snapshot')) {
                $table->string('signatory_name_snapshot')->nullable()->after('signature_path_snapshot');
            }
            if (! Schema::hasColumn('crm_quotations', 'signatory_designation_snapshot')) {
                $table->string('signatory_designation_snapshot')->nullable()->after('signatory_name_snapshot');
            }
        });

        Schema::table('crm_proforma_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_proforma_invoices', 'tax_mode')) {
                $table->string('tax_mode', 16)->default('gst')->after('currency');
            }
            if (! Schema::hasColumn('crm_proforma_invoices', 'show_authorized_signature')) {
                $table->boolean('show_authorized_signature')->default(true)->after('terms_conditions');
            }
            if (! Schema::hasColumn('crm_proforma_invoices', 'signature_path_snapshot')) {
                $table->string('signature_path_snapshot')->nullable()->after('show_authorized_signature');
            }
            if (! Schema::hasColumn('crm_proforma_invoices', 'signatory_name_snapshot')) {
                $table->string('signatory_name_snapshot')->nullable()->after('signature_path_snapshot');
            }
            if (! Schema::hasColumn('crm_proforma_invoices', 'signatory_designation_snapshot')) {
                $table->string('signatory_designation_snapshot')->nullable()->after('signatory_name_snapshot');
            }
        });
    }

    public function down(): void
    {
        // Document snapshots are deliberately retained to keep historical prints stable.
        Schema::dropIfExists('sales_document_settings');
    }
};
