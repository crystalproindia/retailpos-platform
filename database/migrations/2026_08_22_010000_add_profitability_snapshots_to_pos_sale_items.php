<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sale_items', function (Blueprint $table): void {
            // Nullable preserves pre-Phase P1 history without inventing a historical cost.
            $table->foreignId('brand_id_snapshot')->nullable()->after('category_id')->constrained('inventory_brands')->nullOnDelete();
            $table->string('category_name_snapshot')->nullable()->after('category_id');
            $table->string('brand_name_snapshot')->nullable()->after('brand_id_snapshot');
            $table->decimal('unit_cost_snapshot', 14, 2)->nullable()->after('unit_price');
            $table->decimal('total_cost_snapshot', 14, 2)->nullable()->after('unit_cost_snapshot');
            $table->decimal('gross_sales_snapshot', 14, 2)->nullable()->after('gross_amount');
            $table->decimal('net_sales_snapshot', 14, 2)->nullable()->after('taxable_amount');
            $table->decimal('gross_profit_before_discount', 14, 2)->nullable()->after('net_sales_snapshot');
            $table->decimal('gross_profit_snapshot', 14, 2)->nullable()->after('gross_profit_before_discount');
            $table->decimal('gross_margin_before_discount_percent', 9, 4)->nullable()->after('gross_profit_snapshot');
            $table->decimal('gross_margin_percent_snapshot', 9, 4)->nullable()->after('gross_margin_before_discount_percent');
            $table->string('cost_snapshot_method', 32)->nullable()->after('gross_margin_percent_snapshot');
            $table->string('cost_snapshot_status', 24)->nullable()->after('cost_snapshot_method');
            $table->index(['company_id', 'pos_sale_id', 'cost_snapshot_status'], 'pos_sale_item_cost_snapshot_idx');
            $table->index(['company_id', 'product_id', 'brand_id_snapshot'], 'pos_sale_item_product_brand_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sale_items', function (Blueprint $table): void {
            $table->dropIndex('pos_sale_item_cost_snapshot_idx');
            $table->dropIndex('pos_sale_item_product_brand_idx');
            $table->dropConstrainedForeignId('brand_id_snapshot');
            $table->dropColumn([
                'category_name_snapshot', 'brand_name_snapshot', 'unit_cost_snapshot', 'total_cost_snapshot',
                'gross_sales_snapshot', 'net_sales_snapshot', 'gross_profit_before_discount', 'gross_profit_snapshot',
                'gross_margin_before_discount_percent', 'gross_margin_percent_snapshot', 'cost_snapshot_method', 'cost_snapshot_status',
            ]);
        });
    }
};
