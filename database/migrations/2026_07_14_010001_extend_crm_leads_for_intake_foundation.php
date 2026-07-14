<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table): void {
            $table->string('city')->nullable()->after('industry');
            $table->string('country')->nullable()->after('city');
            $table->string('business_type')->nullable()->after('country');
            $table->string('expected_timeline')->nullable()->after('expected_value');
            $table->json('metadata')->nullable()->after('description');
            $table->timestamp('won_at')->nullable()->after('converted_at');
            $table->timestamp('lost_at')->nullable()->after('won_at');

            $table->index(['company_id', 'source_id'], 'crm_lead_company_source_idx');
            $table->index(['company_id', 'priority'], 'crm_lead_company_priority_idx');
            $table->index(['company_id', 'email'], 'crm_lead_company_email_idx');
            $table->index(['company_id', 'phone'], 'crm_lead_company_phone_idx');
            $table->index(['company_id', 'created_at'], 'crm_lead_company_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table): void {
            $table->dropIndex('crm_lead_company_source_idx');
            $table->dropIndex('crm_lead_company_priority_idx');
            $table->dropIndex('crm_lead_company_email_idx');
            $table->dropIndex('crm_lead_company_phone_idx');
            $table->dropIndex('crm_lead_company_created_idx');
            $table->dropColumn(['city', 'country', 'business_type', 'expected_timeline', 'metadata', 'won_at', 'lost_at']);
        });
    }
};
