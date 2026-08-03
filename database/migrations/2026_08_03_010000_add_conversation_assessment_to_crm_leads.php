<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table): void {
            // Keeps a browser retry tied to its original internal lead submission.
            $table->uuid('creation_token')->nullable()->after('lead_score');
            $table->unsignedTinyInteger('client_receptiveness_rating')->nullable()->after('creation_token');
            $table->unsignedTinyInteger('buying_interest_rating')->nullable()->after('client_receptiveness_rating');
            $table->unsignedTinyInteger('follow_up_urgency_rating')->nullable()->after('buying_interest_rating');

            $table->unique(['company_id', 'creation_token'], 'crm_lead_company_token_uq');
            $table->index(['company_id', 'follow_up_urgency_rating'], 'crm_lead_company_urgency_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table): void {
            $table->dropUnique('crm_lead_company_token_uq');
            $table->dropIndex('crm_lead_company_urgency_idx');
            $table->dropColumn([
                'creation_token',
                'client_receptiveness_rating',
                'buying_interest_rating',
                'follow_up_urgency_rating',
            ]);
        });
    }
};
