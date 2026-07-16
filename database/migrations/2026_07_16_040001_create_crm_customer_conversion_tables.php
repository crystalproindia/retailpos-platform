<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('crm_quotations')->nullOnDelete();
            $table->string('customer_code', 32);
            $table->string('company_name');
            $table->string('display_name');
            $table->string('business_type')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('tax_number')->nullable();
            $table->unsignedInteger('number_of_stores')->nullable();
            $table->string('status', 24)->default('onboarding');
            $table->string('source')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'customer_code'], 'crm_cust_company_code_uq');
            $table->unique(['company_id', 'lead_id'], 'crm_cust_company_lead_uq');
            $table->unique(['company_id', 'quotation_id'], 'crm_cust_company_quote_uq');
            $table->index(['company_id', 'status'], 'crm_cust_company_status_idx');
            $table->index(['company_id', 'business_type'], 'crm_cust_company_type_idx');
            $table->index(['company_id', 'created_at'], 'crm_cust_company_created_idx');
            $table->index(['company_id', 'email'], 'crm_cust_company_email_idx');
            $table->index(['company_id', 'phone'], 'crm_cust_company_phone_idx');
        });

        Schema::create('crm_customer_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('designation')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'is_primary'], 'crm_cust_contact_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_contacts');
        Schema::dropIfExists('crm_customers');
    }
};
