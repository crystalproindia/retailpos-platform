<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->string('color', 24)->nullable();
            $table->string('tone', 24)->default('neutral');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('crm_lead_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('stage_type')->default('new');
            $table->string('color', 24)->nullable();
            $table->string('tone', 24)->default('neutral');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('crm_companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('website')->nullable();
            $table->string('industry')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->text('address')->nullable();
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'assigned_user_id']);
        });

        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crm_company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->string('preferred_contact_method')->default('phone');
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'email']);
            $table->index(['company_id', 'assigned_user_id']);
        });

        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crm_company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('crm_lead_sources')->nullOnDelete();
            $table->foreignId('status_id')->constrained('crm_lead_statuses')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('business_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->string('industry')->nullable();
            $table->json('interested_modules')->nullable();
            $table->decimal('expected_value', 12, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('priority')->default('medium');
            $table->unsignedTinyInteger('lead_score')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->longText('description')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status_id']);
            $table->index(['company_id', 'assigned_user_id']);
            $table->index(['company_id', 'next_follow_up_at']);
        });

        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('crm_company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('task');
            $table->string('subject');
            $table->longText('description')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('outcome')->nullable();
            $table->string('priority')->default('medium');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'scheduled_at']);
            $table->index(['company_id', 'assigned_user_id']);
        });

        Schema::create('crm_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('notable');
            $table->longText('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('crm_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 24)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('crm_company_tag', function (Blueprint $table): void {
            $table->foreignId('crm_company_id')->constrained('crm_companies')->cascadeOnDelete();
            $table->foreignId('crm_tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->primary(['crm_company_id', 'crm_tag_id']);
        });

        Schema::create('crm_contact_tag', function (Blueprint $table): void {
            $table->foreignId('crm_contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('crm_tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->primary(['crm_contact_id', 'crm_tag_id']);
        });

        Schema::create('crm_lead_tag', function (Blueprint $table): void {
            $table->foreignId('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('crm_tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->primary(['crm_lead_id', 'crm_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_tag');
        Schema::dropIfExists('crm_contact_tag');
        Schema::dropIfExists('crm_company_tag');
        Schema::dropIfExists('crm_tags');
        Schema::dropIfExists('crm_notes');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_companies');
        Schema::dropIfExists('crm_lead_statuses');
        Schema::dropIfExists('crm_lead_sources');
    }
};
