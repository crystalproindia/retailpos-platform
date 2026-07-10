<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cms_media_folders')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'parent_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media_folders');
    }
};
