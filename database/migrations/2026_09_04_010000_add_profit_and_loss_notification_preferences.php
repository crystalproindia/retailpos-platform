<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_automation_settings', function (Blueprint $table): void {
            $table->boolean('monthly_expense_summary_enabled')->default(false)->after('weekly_summary_enabled');
            $table->boolean('monthly_profit_and_loss_summary_enabled')->default(false)->after('monthly_expense_summary_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('notification_automation_settings', function (Blueprint $table): void {
            $table->dropColumn(['monthly_expense_summary_enabled', 'monthly_profit_and_loss_summary_enabled']);
        });
    }
};
