<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_homepage_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('cms_homepage_sections')->cascadeOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('link_label')->nullable();
            $table->string('link_url')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_homepage_section_items');
    }
};
