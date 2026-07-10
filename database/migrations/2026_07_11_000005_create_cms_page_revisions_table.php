<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('hero_content')->nullable();
            $table->longText('body_content')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->unique(['page_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_revisions');
    }
};
