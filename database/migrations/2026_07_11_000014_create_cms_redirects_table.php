<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_url');
            $table->string('target_url');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'source_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_redirects');
    }
};
