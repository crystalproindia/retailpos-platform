<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('quotation_number', 32);
            $table->string('title');
            $table->string('customer_name')->nullable();
            $table->string('customer_company')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->string('status', 24)->default('draft');
            $table->longText('notes')->nullable();
            $table->longText('terms_conditions')->nullable();
            $table->longText('internal_remarks')->nullable();
            $table->string('public_token', 64)->nullable();
            $table->string('public_url', 2048)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'quotation_number'], 'crm_quote_company_number_uq');
            $table->unique('public_token', 'crm_quote_public_token_uq');
            $table->index(['company_id', 'status'], 'crm_quote_company_status_idx');
            $table->index(['company_id', 'created_at'], 'crm_quote_company_created_idx');
            $table->index(['company_id', 'valid_until'], 'crm_quote_company_valid_idx');
            $table->index(['company_id', 'customer_email'], 'crm_quote_company_email_idx');
            $table->index('lead_id', 'crm_quote_lead_idx');
        });

        Schema::create('crm_quotation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained('crm_quotations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 8, 3)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['quotation_id', 'sort_order'], 'crm_quote_item_quote_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quotation_items');
        Schema::dropIfExists('crm_quotations');
    }
};
