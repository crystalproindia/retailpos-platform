<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('pos_return_item_id')->nullable()->after('product_id')->constrained('pos_return_items')->nullOnDelete();
            $table->unique('pos_return_item_id', 'stock_move_return_item_unique');
        });

        Schema::table('pos_refunds', function (Blueprint $table): void {
            $table->unique(['company_id', 'external_reference'], 'pos_refund_company_ext_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pos_refunds', function (Blueprint $table): void {
            $table->dropUnique('pos_refund_company_ext_ref_unique');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropUnique('stock_move_return_item_unique');
            $table->dropConstrainedForeignId('pos_return_item_id');
        });
    }
};
