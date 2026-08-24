<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_product_favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'branch_id', 'user_id', 'product_id'], 'pos_fav_company_branch_user_product_uq');
            $table->index(['company_id', 'branch_id', 'user_id'], 'pos_fav_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_product_favorites');
    }
};
