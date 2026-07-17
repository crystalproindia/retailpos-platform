<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_onboarding_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('crm_customer_onboardings')->cascadeOnDelete();
            $table->string('task_key', 100);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 40);
            $table->string('status', 20)->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['onboarding_id', 'task_key'], 'crm_onboarding_tasks_onboard_key_uq');
            $table->index(['onboarding_id', 'category', 'sort_order'], 'crm_onboarding_tasks_onboard_category_ix');
            $table->index(['assigned_to', 'status', 'due_date'], 'crm_onboarding_tasks_assignee_status_due_ix');
        });

        Schema::create('crm_onboarding_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('crm_customer_onboardings')->cascadeOnDelete();
            $table->text('note');
            $table->string('visibility', 20)->default('internal');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['onboarding_id', 'created_at'], 'crm_onboarding_notes_onboard_created_ix');
        });

        Schema::create('crm_onboarding_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('crm_customer_onboardings')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('title');
            $table->string('file_path', 1000)->nullable();
            $table->string('external_url', 1000)->nullable();
            $table->string('status', 20)->default('requested');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['onboarding_id', 'status'], 'crm_onboarding_docs_onboard_status_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_onboarding_documents');
        Schema::dropIfExists('crm_onboarding_notes');
        Schema::dropIfExists('crm_onboarding_tasks');
    }
};
