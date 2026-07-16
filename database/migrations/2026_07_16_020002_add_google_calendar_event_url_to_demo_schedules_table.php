<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_schedules', function (Blueprint $table): void {
            $table->string('external_calendar_event_url', 2048)->nullable()->after('external_calendar_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('demo_schedules', function (Blueprint $table): void {
            $table->dropColumn('external_calendar_event_url');
        });
    }
};
