<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('crm_invoice_items', function (Blueprint $table): void {
        $table->foreignId('product_id')->nullable()->after('invoice_id')->constrained('products')->nullOnDelete();
        $table->foreignId('category_id_snapshot')->nullable()->after('product_id')->constrained('inventory_categories')->nullOnDelete();
        $table->foreignId('brand_id_snapshot')->nullable()->after('category_id_snapshot')->constrained('inventory_brands')->nullOnDelete();
        $table->string('sku_snapshot')->nullable()->after('name'); $table->string('category_name_snapshot')->nullable(); $table->string('brand_name_snapshot')->nullable();
        $table->decimal('unit_cost_snapshot',14,2)->nullable(); $table->decimal('total_cost_snapshot',14,2)->nullable();
        $table->decimal('gross_sales_snapshot',14,2)->nullable(); $table->decimal('net_sales_snapshot',14,2)->nullable();
        $table->decimal('gross_profit_before_discount',14,2)->nullable(); $table->decimal('gross_profit_snapshot',14,2)->nullable();
        $table->decimal('gross_margin_percent_snapshot',9,4)->nullable(); $table->string('cost_snapshot_method',32)->nullable(); $table->string('cost_snapshot_status',24)->nullable();
        $table->index(['product_id','cost_snapshot_status'], 'crm_inv_item_product_cost_idx');
    }); }
    public function down(): void { Schema::table('crm_invoice_items', function (Blueprint $table): void { $table->dropIndex('crm_inv_item_product_cost_idx'); $table->dropConstrainedForeignId('product_id'); $table->dropConstrainedForeignId('category_id_snapshot'); $table->dropConstrainedForeignId('brand_id_snapshot'); $table->dropColumn(['sku_snapshot','category_name_snapshot','brand_name_snapshot','unit_cost_snapshot','total_cost_snapshot','gross_sales_snapshot','net_sales_snapshot','gross_profit_before_discount','gross_profit_snapshot','gross_margin_percent_snapshot','cost_snapshot_method','cost_snapshot_status']); }); }
};
