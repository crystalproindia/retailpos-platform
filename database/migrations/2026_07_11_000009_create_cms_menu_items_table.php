<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('cms_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cms_menu_items')->nullOnDelete();
            $table->string('label');
            $table->string('url');
            $table->string('route_name')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('opens_new_tab')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menu_items');
    }
};
