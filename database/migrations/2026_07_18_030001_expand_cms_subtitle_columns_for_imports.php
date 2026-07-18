<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', fn (Blueprint $table) => $table->text('subtitle')->nullable()->change());
        Schema::table('cms_page_sections', fn (Blueprint $table) => $table->text('subtitle')->nullable()->change());
        Schema::table('cms_case_study_sections', fn (Blueprint $table) => $table->text('subtitle')->nullable()->change());
        Schema::table('cms_page_revisions', fn (Blueprint $table) => $table->text('subtitle')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('cms_pages', fn (Blueprint $table) => $table->string('subtitle')->nullable()->change());
        Schema::table('cms_page_sections', fn (Blueprint $table) => $table->string('subtitle')->nullable()->change());
        Schema::table('cms_case_study_sections', fn (Blueprint $table) => $table->string('subtitle')->nullable()->change());
        Schema::table('cms_page_revisions', fn (Blueprint $table) => $table->string('subtitle')->nullable()->change());
    }
};
