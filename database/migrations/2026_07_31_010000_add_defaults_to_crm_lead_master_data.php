<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_lead_statuses') && ! Schema::hasColumn('crm_lead_statuses', 'is_default')) {
            Schema::table('crm_lead_statuses', function (Blueprint $table): void {
                $table->boolean('is_default')->default(false)->after('is_active');
            });
        }

        if (Schema::hasTable('crm_lead_sources') && ! Schema::hasColumn('crm_lead_sources', 'is_default')) {
            Schema::table('crm_lead_sources', function (Blueprint $table): void {
                $table->boolean('is_default')->default(false)->after('is_active');
            });
        }

        if (! Schema::hasTable('crm_lead_statuses') || ! Schema::hasColumn('crm_lead_statuses', 'is_default')) {
            return;
        }

        DB::table('crm_lead_statuses')
            ->select('company_id')
            ->distinct()
            ->orderBy('company_id')
            ->each(function (object $record): void {
                $statuses = DB::table('crm_lead_statuses')
                    ->where('company_id', $record->company_id)
                    ->whereNull('deleted_at');

                if ((clone $statuses)->where('is_default', true)->exists()) {
                    return;
                }

                $defaultId = (clone $statuses)
                    ->where('slug', 'new')
                    ->value('id');

                if ($defaultId) {
                    DB::table('crm_lead_statuses')->where('id', $defaultId)->update(['is_default' => true]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_lead_statuses') && Schema::hasColumn('crm_lead_statuses', 'is_default')) {
            Schema::table('crm_lead_statuses', function (Blueprint $table): void {
                $table->dropColumn('is_default');
            });
        }

        if (Schema::hasTable('crm_lead_sources') && Schema::hasColumn('crm_lead_sources', 'is_default')) {
            Schema::table('crm_lead_sources', function (Blueprint $table): void {
                $table->dropColumn('is_default');
            });
        }
    }
};
