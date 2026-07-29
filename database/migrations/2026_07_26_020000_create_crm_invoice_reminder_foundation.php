<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_invoice_reminder_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('automatic_enabled')->default(false);
            $table->unsignedSmallInteger('minimum_cooldown_hours')->default(24);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id', 'crm_inv_rem_settings_company_uq');
        });

        Schema::create('crm_invoice_reminder_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reminder_setting_id')->constrained('crm_invoice_reminder_settings')->cascadeOnDelete();
            $table->string('stage', 32);
            $table->boolean('enabled')->default(true);
            $table->smallInteger('offset_days');
            $table->boolean('attach_pdf')->default(true);
            $table->boolean('include_secure_link')->default(true);
            $table->string('subject', 180);
            $table->text('intro_message');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'stage'], 'crm_inv_rem_rule_company_stage_uq');
            $table->index(['company_id', 'enabled', 'offset_days'], 'crm_inv_rem_rule_company_timing_idx');
        });

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->string('reminder_stage', 32)->nullable()->after('template_key');
            $table->string('reminder_source', 16)->nullable()->after('reminder_stage');
            $table->index(['company_id', 'related_type', 'related_id', 'reminder_stage'], 'notif_deliv_reminder_stage_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropIndex('notif_deliv_reminder_stage_idx');
            $table->dropColumn(['reminder_stage', 'reminder_source']);
        });

        Schema::dropIfExists('crm_invoice_reminder_rules');
        Schema::dropIfExists('crm_invoice_reminder_settings');
    }
};
