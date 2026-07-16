<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->date('scheduled_date');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 64)->default('UTC');
            $table->string('meeting_mode');
            $table->string('meeting_link', 2048)->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('external_calendar_provider')->nullable();
            $table->string('external_calendar_event_id')->nullable();
            $table->string('external_meeting_link', 2048)->nullable();
            $table->string('calendar_sync_status')->nullable();
            $table->timestamp('calendar_synced_at')->nullable();
            $table->timestamps();

            $table->index('lead_id', 'demo_sched_lead_idx');
            $table->index('assigned_to', 'demo_sched_assignee_idx');
            $table->index('scheduled_date', 'demo_sched_date_idx');
            $table->index('starts_at', 'demo_sched_starts_idx');
            $table->index('status', 'demo_sched_status_idx');
            $table->index(['company_id', 'scheduled_date', 'status'], 'demo_sched_company_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_schedules');
    }
};
