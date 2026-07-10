<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_page_seo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->foreignId('og_image_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('og_type')->nullable();
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->foreignId('twitter_image_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('twitter_card')->default('summary_large_image');
            $table->timestamps();

            $table->unique('page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_seo');
    }
};
