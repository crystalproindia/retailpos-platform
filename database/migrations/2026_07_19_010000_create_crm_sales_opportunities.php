<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('crm_company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('stage', 32)->default('qualified');
            $table->decimal('expected_value', 14, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->unsignedTinyInteger('probability_percentage')->default(25);
            $table->date('expected_close_date')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('loss_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'stage'], 'crm_opp_company_stage_idx');
            $table->index(['company_id', 'assigned_user_id'], 'crm_opp_company_owner_idx');
            $table->index(['company_id', 'expected_close_date'], 'crm_opp_company_close_idx');
            $table->index(['company_id', 'lead_id'], 'crm_opp_company_lead_idx');
        });

        Schema::create('crm_opportunity_stage_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('crm_opportunities')->cascadeOnDelete();
            $table->string('from_stage', 32)->nullable();
            $table->string('to_stage', 32);
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['opportunity_id', 'changed_at'], 'crm_opp_history_opp_changed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunity_stage_histories');
        Schema::dropIfExists('crm_opportunities');
    }
};
