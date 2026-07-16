<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->string('category', 32);
            $table->string('confidence', 16);
            $table->string('priority', 16);
            $table->string('next_best_action')->nullable();
            $table->json('reasons')->nullable();
            $table->json('risks')->nullable();
            $table->json('opportunities')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('analyzed_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('lead_id', 'crm_lead_scores_lead_unique');
            $table->index(['company_id', 'category', 'priority'], 'crm_lead_scores_company_cat_pri_idx');
            $table->index(['company_id', 'analyzed_at'], 'crm_lead_scores_company_analyzed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_scores');
    }
};
