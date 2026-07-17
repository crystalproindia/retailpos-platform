<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_onboardings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('crm_quotations')->nullOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->constrained('crm_proforma_invoices')->nullOnDelete();
            $table->string('onboarding_number', 40);
            $table->string('title');
            $table->string('status', 40)->default('not_started');
            $table->string('priority', 20)->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('implementation_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('target_go_live_date')->nullable();
            $table->date('actual_go_live_date')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('customer_contact_name')->nullable();
            $table->string('customer_contact_phone')->nullable();
            $table->string('customer_contact_email')->nullable();
            $table->string('business_name')->nullable();
            $table->unsignedSmallInteger('store_count')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'onboarding_number'], 'crm_onboardings_company_number_uq');
            $table->index(['company_id', 'customer_id', 'status'], 'crm_onboardings_company_customer_status_ix');
            $table->index(['company_id', 'assigned_to', 'status'], 'crm_onboardings_company_assigned_status_ix');
            $table->index(['company_id', 'target_go_live_date'], 'crm_onboardings_company_target_live_ix');
            $table->index(['company_id', 'created_at'], 'crm_onboardings_company_created_ix');
        });
    }

    public function down(): void { Schema::dropIfExists('crm_customer_onboardings'); }
};
