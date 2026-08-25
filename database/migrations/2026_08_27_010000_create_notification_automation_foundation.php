<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_automation_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('low_stock_enabled')->default(true);
            $table->boolean('out_of_stock_enabled')->default(true);
            $table->boolean('reorder_enabled')->default(true);
            $table->boolean('payment_reminders_enabled')->default(true);
            $table->json('payment_before_due_days')->nullable();
            $table->json('payment_overdue_days')->nullable();
            $table->boolean('customer_payment_emails_enabled')->default(false);
            $table->boolean('quotation_expiry_enabled')->default(true);
            $table->boolean('proforma_expiry_enabled')->default(true);
            $table->unsignedSmallInteger('document_expiry_notice_days')->default(3);
            $table->boolean('purchase_reminders_enabled')->default(true);
            $table->boolean('internal_email_enabled')->default(false);
            $table->boolean('daily_summary_enabled')->default(false);
            $table->boolean('weekly_summary_enabled')->default(false);
            $table->time('summary_time')->default('08:00');
            $table->string('timezone')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id', 'notif_auto_settings_company_uq');
        });

        Schema::create('notification_condition_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('condition_type', 64);
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id');
            $table->string('stage', 80);
            $table->string('severity', 24)->default('attention');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('cycle')->default(1);
            $table->json('context')->nullable();
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'condition_type', 'subject_type', 'subject_id', 'stage'], 'notif_cond_company_type_subject_stage_uq');
            $table->index(['company_id', 'condition_type', 'is_active'], 'notif_cond_company_type_active_idx');
            $table->index(['company_id', 'branch_id', 'is_active'], 'notif_cond_company_branch_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_condition_states');
        Schema::dropIfExists('notification_automation_settings');
    }
};
