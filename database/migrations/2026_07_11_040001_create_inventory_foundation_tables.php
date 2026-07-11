<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('inventory_brands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('inventory_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_code');
            $table->string('type')->default('quantity');
            $table->boolean('decimal_allowed')->default(false);
            $table->decimal('conversion_factor', 14, 6)->nullable();
            $table->foreignId('base_unit_id')->nullable()->constrained('inventory_units')->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'short_code']);
        });

        Schema::create('inventory_tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('rate', 8, 3)->default(0);
            $table->string('tax_type')->default('gst');
            $table->string('country', 80)->default('India');
            $table->string('state', 120)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'tax_type', 'is_active']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('inventory_brands')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained('inventory_tax_rates')->nullOnDelete();
            $table->foreignId('parent_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('type')->default('simple');
            $table->string('name');
            $table->string('slug');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('hsn_code')->nullable();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('cost_price', 14, 2)->nullable();
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('mrp', 14, 2)->nullable();
            $table->decimal('wholesale_price', 14, 2)->nullable();
            $table->decimal('online_price', 14, 2)->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('has_variants')->default(false);
            $table->boolean('is_variant')->default(false);
            $table->string('variant_name')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'sku']);
            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'barcode']);
            $table->index(['company_id', 'category_id', 'brand_id']);
        });

        Schema::create('product_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->default('text');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->string('value');
            $table->string('slug');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['attribute_id', 'slug']);
        });

        Schema::create('product_variant_attributes', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained('product_attribute_values')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'attribute_id']);
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('type')->default('store');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('India');
            $table->string('postal_code')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('stock_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('type')->default('bin');
            $table->string('aisle')->nullable();
            $table->string('rack')->nullable();
            $table->string('shelf')->nullable();
            $table->string('bin')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('stock_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->decimal('quantity_reserved', 14, 3)->default(0);
            $table->decimal('quantity_available', 14, 3)->default(0);
            $table->decimal('reorder_point', 14, 3)->nullable();
            $table->decimal('reorder_quantity', 14, 3)->nullable();
            $table->decimal('minimum_stock', 14, 3)->nullable();
            $table->decimal('maximum_stock', 14, 3)->nullable();
            $table->decimal('safety_stock', 14, 3)->nullable();
            $table->unsignedBigInteger('preferred_supplier_id')->nullable();
            $table->unsignedInteger('supplier_lead_time_days')->nullable();
            $table->decimal('average_daily_sales', 14, 3)->nullable();
            $table->timestamp('last_stock_movement_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'warehouse_id', 'stock_location_id', 'product_id'], 'stock_levels_scope_unique');
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('movement_type');
            $table->string('direction');
            $table->decimal('quantity', 14, 3);
            $table->decimal('quantity_before', 14, 3);
            $table->decimal('quantity_after', 14, 3);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['company_id', 'product_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('adjustment_number');
            $table->string('status')->default('draft');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'adjustment_number']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->decimal('current_quantity', 14, 3);
            $table->decimal('adjusted_quantity', 14, 3);
            $table->decimal('difference', 14, 3);
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('barcode_label_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('industry_type')->nullable();
            $table->string('paper_size')->nullable();
            $table->decimal('label_width_mm', 8, 2);
            $table->decimal('label_height_mm', 8, 2);
            $table->unsignedInteger('columns')->default(1);
            $table->unsignedInteger('rows')->nullable();
            $table->decimal('gap_horizontal_mm', 8, 2)->default(0);
            $table->decimal('gap_vertical_mm', 8, 2)->default(0);
            $table->decimal('margin_top_mm', 8, 2)->default(0);
            $table->decimal('margin_right_mm', 8, 2)->default(0);
            $table->decimal('margin_bottom_mm', 8, 2)->default(0);
            $table->decimal('margin_left_mm', 8, 2)->default(0);
            $table->string('barcode_type')->default('CODE128');
            $table->decimal('barcode_width_mm', 8, 2)->nullable();
            $table->decimal('barcode_height_mm', 8, 2)->nullable();
            $table->unsignedInteger('font_size')->default(10);
            $table->boolean('show_product_name')->default(true);
            $table->boolean('show_sku')->default(true);
            $table->boolean('show_barcode_text')->default(true);
            $table->boolean('show_price')->default(true);
            $table->boolean('show_mrp')->default(false);
            $table->boolean('show_offer_price')->default(false);
            $table->boolean('show_brand')->default(false);
            $table->boolean('show_category')->default(false);
            $table->boolean('show_size')->default(false);
            $table->boolean('show_color')->default(false);
            $table->boolean('show_batch')->default(false);
            $table->boolean('show_expiry')->default(false);
            $table->boolean('show_company_name')->default(false);
            $table->boolean('show_logo')->default(false);
            $table->text('custom_css')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('barcode_print_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('barcode_label_templates')->restrictOnDelete();
            $table->string('batch_number');
            $table->string('title')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedInteger('total_labels')->default(0);
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'batch_number']);
        });

        Schema::create('barcode_print_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('print_batch_id')->constrained('barcode_print_batches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price_override', 14, 2)->nullable();
            $table->json('label_data')->nullable();
            $table->timestamps();
        });

        Schema::create('reorder_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('minimum_stock', 14, 3);
            $table->decimal('maximum_stock', 14, 3)->nullable();
            $table->decimal('reorder_point', 14, 3);
            $table->decimal('reorder_quantity', 14, 3);
            $table->decimal('safety_stock', 14, 3)->nullable();
            $table->unsignedInteger('supplier_lead_time_days')->nullable();
            $table->unsignedBigInteger('preferred_supplier_id')->nullable();
            $table->decimal('average_daily_sales', 14, 3)->nullable();
            $table->decimal('seasonal_factor', 8, 3)->nullable();
            $table->boolean('auto_generate_purchase_request')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'warehouse_id', 'product_id']);
        });

        Schema::create('reorder_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('current_stock', 14, 3);
            $table->decimal('available_stock', 14, 3);
            $table->decimal('reorder_point', 14, 3);
            $table->decimal('suggested_quantity', 14, 3);
            $table->string('stockout_risk_level');
            $table->date('estimated_stockout_date')->nullable();
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'stockout_risk_level']);
        });

        Schema::create('sales_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('type');
            $table->text('description')->nullable();
            $table->boolean('is_online')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('sync_enabled')->default(false);
            $table->string('price_strategy')->default('selling_price');
            $table->string('stock_strategy')->default('available_stock');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('channel_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_channel_id')->constrained('sales_channels')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('channel_sku')->nullable();
            $table->string('channel_product_name')->nullable();
            $table->decimal('channel_price', 14, 2)->nullable();
            $table->decimal('channel_mrp', 14, 2)->nullable();
            $table->decimal('channel_offer_price', 14, 2)->nullable();
            $table->decimal('stock_buffer_quantity', 14, 3)->nullable();
            $table->decimal('max_listed_quantity', 14, 3)->nullable();
            $table->boolean('sync_product')->default(true);
            $table->boolean('sync_price')->default(true);
            $table->boolean('sync_stock')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('not_synced');
            $table->text('sync_error')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['sales_channel_id', 'product_id']);
        });

        Schema::create('channel_stock_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_channel_id')->constrained('sales_channels')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('listed_quantity', 14, 3)->default(0);
            $table->decimal('reserved_quantity', 14, 3)->default(0);
            $table->decimal('available_quantity', 14, 3)->default(0);
            $table->decimal('buffer_quantity', 14, 3)->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('not_synced');
            $table->timestamps();
            $table->unique(['sales_channel_id', 'product_id', 'warehouse_id'], 'channel_stock_unique');
        });

        Schema::create('inventory_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_channel_id')->nullable()->constrained('sales_channels')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('action');
            $table->string('status');
            $table->text('message')->nullable();
            $table->json('payload_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sync_logs');
        Schema::dropIfExists('channel_stock_levels');
        Schema::dropIfExists('channel_product_mappings');
        Schema::dropIfExists('sales_channels');
        Schema::dropIfExists('reorder_suggestions');
        Schema::dropIfExists('reorder_rules');
        Schema::dropIfExists('barcode_print_batch_items');
        Schema::dropIfExists('barcode_print_batches');
        Schema::dropIfExists('barcode_label_templates');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('product_variant_attributes');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('inventory_tax_rates');
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('inventory_brands');
        Schema::dropIfExists('inventory_categories');
    }
};
