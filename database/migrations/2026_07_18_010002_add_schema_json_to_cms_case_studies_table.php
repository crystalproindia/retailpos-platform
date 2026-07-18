<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_case_studies', function (Blueprint $table): void {
            $table->json('schema_json')->nullable()->after('seo_description');
        });
    }

    public function down(): void
    {
        Schema::table('cms_case_studies', function (Blueprint $table): void {
            $table->dropColumn('schema_json');
        });
    }
};
