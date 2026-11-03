<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_billing_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
            $table->string('profile_name');
            $table->boolean('is_primary')->default(false);
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->index(['company_id', 'customer_id', 'status'], 'crm_cbp_company_customer_status_idx');
            $table->index(['customer_id', 'is_primary'], 'crm_cbp_customer_primary_idx');
        });

        Schema::table('crm_invoices', function (Blueprint $table): void {
            $table->foreignId('billing_profile_id')->nullable()->after('customer_id')->constrained('crm_customer_billing_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('billing_profile_id'));
        Schema::dropIfExists('crm_customer_billing_profiles');
    }
};
