<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_page_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->string('section_key');
            $table->string('section_type');
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->longText('content')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['page_id', 'section_key'], 'cms_pg_sec_page_key_ix');
            $table->index(['company_id', 'page_id'], 'cms_pg_sec_company_page_ix');
            $table->index(['page_id', 'sort_order'], 'cms_pg_sec_page_sort_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_sections');
    }
};
