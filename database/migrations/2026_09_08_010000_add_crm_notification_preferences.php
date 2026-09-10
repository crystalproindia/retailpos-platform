<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_automation_settings', function (Blueprint $table): void {
            // CRM alerts are deliberately opt-in: a company should never gain
            // a new source of operational noise merely by upgrading.
            $table->boolean('crm_overdue_follow_up_enabled')->default(false)->after('proforma_expiry_enabled');
            $table->boolean('crm_stalled_high_priority_enabled')->default(false)->after('crm_overdue_follow_up_enabled');
            $table->boolean('crm_follow_up_digest_enabled')->default(false)->after('crm_stalled_high_priority_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('notification_automation_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'crm_overdue_follow_up_enabled',
                'crm_stalled_high_priority_enabled',
                'crm_follow_up_digest_enabled',
            ]);
        });
    }
};
