<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('featured_image_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('hero_content')->nullable();
            $table->longText('body_content')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
