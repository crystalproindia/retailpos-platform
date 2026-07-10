<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('heading')->nullable();
            $table->string('subheading')->nullable();
            $table->longText('content')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_homepage_sections');
    }
};
