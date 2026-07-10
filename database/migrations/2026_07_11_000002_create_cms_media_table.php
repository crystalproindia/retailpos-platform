<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('cms_media_folders')->nullOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('file_name');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->string('extension', 20)->nullable();
            $table->string('type')->default('file');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('alt_text')->nullable();
            $table->boolean('is_optimized')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media');
    }
};
