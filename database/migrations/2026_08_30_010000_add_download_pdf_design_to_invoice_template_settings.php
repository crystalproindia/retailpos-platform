<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoice_template_settings', 'download_pdf_design')) {
            Schema::table('invoice_template_settings', function (Blueprint $table): void {
                $table->string('download_pdf_design', 64)->default('retailpos_premium_blue')->after('template_key');
            });
        }

        DB::table('invoice_template_settings')
            ->whereNull('download_pdf_design')
            ->update(['download_pdf_design' => 'retailpos_premium_blue']);
    }

    public function down(): void
    {
        // The setting is presentation-only, but retaining it on rollback avoids
        // erasing a tenant preference from an already-running production system.
    }
};
