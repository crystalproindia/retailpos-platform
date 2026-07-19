<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            $table->foreignId('opportunity_id')->nullable()->after('crm_lead_id')->constrained('crm_opportunities')->nullOnDelete();
            $table->string('follow_up_status', 24)->default('pending')->after('priority');
            $table->string('timezone', 64)->default('UTC')->after('scheduled_at');
            $table->timestamp('reminder_at')->nullable()->after('scheduled_at');
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->index(['company_id', 'assigned_user_id', 'follow_up_status', 'scheduled_at'], 'crm_activity_followup_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            $table->dropIndex('crm_activity_followup_queue_idx');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancelled_at');
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn(['reminder_at', 'timezone', 'follow_up_status']);
            $table->dropConstrainedForeignId('opportunity_id');
        });
    }
};
