<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('value')->nullable();
            $table->string('value_type')->default('text');
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_settings');
    }
};
