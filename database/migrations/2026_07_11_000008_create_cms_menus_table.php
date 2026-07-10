<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->default('header');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'location', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menus');
    }
};
