<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_broken_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_url');
            $table->string('broken_url');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_broken_links');
    }
};
