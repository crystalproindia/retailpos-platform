<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('workforce_employees')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('task_type', 16);
            $table->string('source_type', 24)->default('manual');
            $table->string('related_type', 80)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 24)->default('todo');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('reminder_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('completion_note')->nullable();
            $table->string('recurrence_type', 24)->nullable();
            $table->unsignedSmallInteger('recurrence_interval')->nullable();
            $table->foreignId('recurrence_parent_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->uuid('recurrence_series_id')->nullable();
            $table->timestamp('recurrence_cancelled_at')->nullable();
            $table->string('system_rule_key', 80)->nullable();
            $table->string('idempotency_key', 160)->nullable();
            $table->string('reminder_delivery_state', 24)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'task_type', 'status', 'due_at'], 'task_company_type_status_due_idx');
            $table->index(['company_id', 'assigned_user_id', 'status', 'due_at'], 'task_company_assignee_status_due_idx');
            $table->index(['company_id', 'outlet_id', 'status', 'due_at'], 'task_company_outlet_status_due_idx');
            $table->index(['company_id', 'related_type', 'related_id'], 'task_company_related_idx');
            $table->index(['company_id', 'system_rule_key', 'status'], 'task_company_rule_status_idx');
            $table->index(['recurrence_series_id', 'due_at'], 'task_recurrence_series_due_idx');
            $table->unique(['company_id', 'idempotency_key'], 'task_company_idempotency_unique');
        });

        Schema::create('task_rule_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key', 80);
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('threshold_hours')->nullable();
            $table->json('configuration')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'rule_key'], 'task_rule_company_key_unique');
        });

        Schema::create('task_reminder_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 24);
            $table->string('kind', 24);
            $table->string('status', 24)->default('pending');
            $table->string('idempotency_key', 160);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamps();
            $table->unique(['task_id', 'user_id', 'channel', 'kind'], 'task_reminder_delivery_unique');
            $table->index(['status', 'created_at'], 'task_reminder_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reminder_deliveries');
        Schema::dropIfExists('task_rule_settings');
        Schema::dropIfExists('tasks');
    }
};
