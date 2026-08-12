<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasPaperFormat = Schema::hasColumn('invoice_template_settings', 'paper_format');
        $hasGstPresentation = Schema::hasColumn('invoice_template_settings', 'gst_presentation');

        Schema::table('invoice_template_settings', function (Blueprint $table) use ($hasPaperFormat, $hasGstPresentation): void {
            if (! $hasPaperFormat) {
                $table->string('paper_format', 24)->default('a4')->after('template_key');
            }
            if (! $hasGstPresentation) {
                $table->string('gst_presentation', 24)->default('detailed')->after('orientation');
            }
        });
    }

    public function down(): void
    {
        $hasPaperFormat = Schema::hasColumn('invoice_template_settings', 'paper_format');
        $hasGstPresentation = Schema::hasColumn('invoice_template_settings', 'gst_presentation');

        Schema::table('invoice_template_settings', function (Blueprint $table) use ($hasPaperFormat, $hasGstPresentation): void {
            if ($hasGstPresentation) {
                $table->dropColumn('gst_presentation');
            }
            if ($hasPaperFormat) {
                $table->dropColumn('paper_format');
            }
        });
    }
};
