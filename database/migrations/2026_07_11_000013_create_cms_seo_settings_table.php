<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_seo_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('default_og_image_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('default_meta_title')->nullable();
            $table->text('default_meta_description')->nullable();
            $table->text('default_meta_keywords')->nullable();
            $table->string('default_canonical_url')->nullable();
            $table->longText('schema_markup')->nullable();
            $table->longText('robots_txt')->nullable();
            $table->boolean('sitemap_enabled')->default(true);
            $table->string('search_console_verification')->nullable();
            $table->string('google_analytics_id')->nullable();
            $table->string('google_tag_manager_id')->nullable();
            $table->string('facebook_pixel_id')->nullable();
            $table->string('linkedin_insight_tag')->nullable();
            $table->string('microsoft_clarity_id')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_seo_settings');
    }
};
