<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_navigation_preferences')) {
            return;
        }

        Schema::create('user_navigation_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('hidden_module_ids')->nullable();
            $table->json('pinned_module_ids')->nullable();
            $table->json('module_order')->nullable();
            $table->string('selected_preset', 40)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id'], 'user_nav_pref_company_user_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_navigation_preferences');
    }
};
