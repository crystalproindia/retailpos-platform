<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_schedules', function (Blueprint $table): void {
            $table->string('calendar_sync_error', 500)->nullable()->after('calendar_sync_status');
            $table->unsignedSmallInteger('calendar_sync_attempts')->default(0)->after('calendar_sync_error');
            $table->index(['company_id', 'calendar_sync_status'], 'demo_cal_sync_company_status_idx');
        });

        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->string('last_sync_status', 40)->nullable()->after('last_synced_at');
            $table->string('last_sync_error', 500)->nullable()->after('last_sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('demo_schedules', function (Blueprint $table): void {
            $table->dropIndex('demo_cal_sync_company_status_idx');
            $table->dropColumn(['calendar_sync_error', 'calendar_sync_attempts']);
        });
        Schema::table('integration_connections', fn (Blueprint $table) => $table->dropColumn(['last_sync_status', 'last_sync_error']));
    }
};
