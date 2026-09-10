<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_stage_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('crm_lead_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->constrained('crm_lead_statuses')->restrictOnDelete();
            $table->string('from_stage', 32)->nullable();
            $table->string('to_stage', 32);
            $table->string('event_type', 48)->default('stage_transition');
            $table->uuid('idempotency_key')->nullable();
            $table->string('priority_snapshot', 24)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_user_name_snapshot')->nullable();
            $table->string('lost_reason_snapshot')->nullable();
            $table->text('lost_note_snapshot')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name_snapshot')->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'lead_id', 'idempotency_key'], 'crm_lead_history_idempotency_unique');
            $table->index(['company_id', 'branch_id', 'occurred_at'], 'crm_lead_history_company_branch_time_idx');
            $table->index(['lead_id', 'occurred_at'], 'crm_lead_history_lead_time_idx');
            $table->index(['company_id', 'to_stage', 'occurred_at'], 'crm_lead_history_company_stage_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_stage_histories');
    }
};
