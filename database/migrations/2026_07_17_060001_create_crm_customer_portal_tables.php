<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_portal_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 80)->nullable();
            $table->string('status', 20)->default('invited');
            $table->timestamp('last_login_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'email'], 'crm_portal_user_customer_email_uq');
            $table->index(['customer_id', 'status'], 'crm_portal_user_customer_status_idx');
        });

        Schema::create('crm_customer_portal_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_portal_user_id')->constrained('crm_customer_portal_users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('purpose', 20)->default('login');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['customer_portal_user_id', 'purpose'], 'crm_portal_token_user_purpose_idx');
            $table->index(['expires_at', 'used_at'], 'crm_portal_token_expiry_used_idx');
        });

        Schema::table('crm_leads', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('crm_contact_id')->constrained('crm_customers')->nullOnDelete();
            $table->index(['company_id', 'customer_id'], 'crm_lead_company_customer_idx');
        });

        Schema::table('crm_support_tickets', function (Blueprint $table): void {
            $table->foreignId('customer_portal_user_id')->nullable()->after('customer_id')->constrained('crm_customer_portal_users')->nullOnDelete();
        });

        Schema::table('crm_support_ticket_messages', function (Blueprint $table): void {
            $table->foreignId('customer_portal_user_id')->nullable()->after('created_by')->constrained('crm_customer_portal_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_support_ticket_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_portal_user_id');
        });

        Schema::table('crm_support_tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_portal_user_id');
        });

        Schema::table('crm_leads', function (Blueprint $table): void {
            $table->dropIndex('crm_lead_company_customer_idx');
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('crm_customer_portal_tokens');
        Schema::dropIfExists('crm_customer_portal_users');
    }
};
