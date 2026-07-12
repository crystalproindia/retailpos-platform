<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('supplier_type')->default('other');
            $table->string('tax_id')->nullable();
            $table->string('gstin')->nullable();
            $table->string('pan')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->string('payment_terms')->nullable();
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->string('default_currency', 3)->default('INR');
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->decimal('rating', 5, 2)->nullable();
            $table->decimal('manual_rating', 5, 2)->nullable();
            $table->text('service_notes')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'supplier_type', 'is_active']);
        });

        Schema::create('supplier_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supplier_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('type')->default('office');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('country')->default('India');
            $table->string('postal_code');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supplier_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->string('supplier_product_name')->nullable();
            $table->decimal('purchase_price', 14, 2);
            $table->decimal('mrp', 14, 2)->nullable();
            $table->decimal('minimum_order_quantity', 14, 3)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->foreignId('tax_rate_id')->nullable()->constrained('inventory_tax_rates')->nullOnDelete();
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('last_purchase_price', 14, 2)->nullable();
            $table->timestamp('last_purchased_at')->nullable();
            $table->decimal('product_performance_score', 5, 2)->nullable();
            $table->decimal('price_score', 5, 2)->nullable();
            $table->decimal('delivery_score', 5, 2)->nullable();
            $table->decimal('return_quality_score', 5, 2)->nullable();
            $table->decimal('service_score', 5, 2)->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['supplier_id', 'product_id']);
            $table->index(['company_id', 'product_id', 'is_preferred']);
        });

        Schema::create('supplier_score_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->decimal('product_performance_score', 5, 2)->nullable();
            $table->decimal('price_score', 5, 2)->nullable();
            $table->decimal('delivery_score', 5, 2)->nullable();
            $table->decimal('return_quality_score', 5, 2)->nullable();
            $table->decimal('service_score', 5, 2)->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->decimal('purchase_value', 14, 2)->nullable();
            $table->decimal('received_quantity', 14, 3)->nullable();
            $table->decimal('rejected_quantity', 14, 3)->nullable();
            $table->decimal('returned_quantity', 14, 3)->nullable();
            $table->unsignedInteger('delayed_delivery_count')->nullable();
            $table->timestamp('calculated_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'supplier_id', 'calculated_at']);
        });

        Schema::create('purchase_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('po_prefix')->default('PO');
            $table->string('pr_prefix')->default('PR');
            $table->string('grn_prefix')->default('GRN');
            $table->string('return_prefix')->default('PRN');
            $table->unsignedBigInteger('next_po_number')->default(1);
            $table->unsignedBigInteger('next_pr_number')->default(1);
            $table->unsignedBigInteger('next_grn_number')->default(1);
            $table->unsignedBigInteger('next_return_number')->default(1);
            $table->boolean('require_po_approval')->default(true);
            $table->boolean('require_purchase_request_approval')->default(true);
            $table->boolean('require_return_approval')->default(true);
            $table->string('default_payment_terms')->nullable();
            $table->boolean('default_tax_inclusive')->default(false);
            $table->boolean('allow_receive_without_po')->default(false);
            $table->boolean('auto_create_pr_from_reorder')->default(false);
            $table->timestamps();
            $table->unique('company_id');
        });

        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('request_number');
            $table->string('source_type')->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status')->default('draft');
            $table->string('priority')->default('normal');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->date('expected_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'request_number']);
            $table->index(['company_id', 'status', 'priority']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('requested_quantity', 14, 3);
            $table->decimal('approved_quantity', 14, 3)->nullable();
            $table->decimal('estimated_price', 14, 2)->nullable();
            $table->date('expected_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->string('po_number');
            $table->string('status')->default('draft');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'po_number']);
            $table->index(['company_id', 'status', 'order_date']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->nullable()->constrained('supplier_products')->nullOnDelete();
            $table->string('product_name_snapshot');
            $table->string('sku_snapshot')->nullable();
            $table->decimal('ordered_quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->decimal('pending_quantity', 14, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 8, 3)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('grn_number');
            $table->date('receipt_date');
            $table->string('status')->default('draft');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->string('supplier_invoice_number')->nullable();
            $table->date('supplier_invoice_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'grn_number']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->decimal('ordered_quantity', 14, 3)->nullable();
            $table->decimal('received_quantity', 14, 3);
            $table->decimal('accepted_quantity', 14, 3);
            $table->decimal('rejected_quantity', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 2);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('manufacture_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->string('return_number');
            $table->string('status')->default('draft');
            $table->date('return_date');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'return_number']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2);
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_approval_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('comments')->nullable();
            $table->timestamps();
            $table->index(['approvable_type', 'approvable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_approval_logs');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('purchase_settings');
        Schema::dropIfExists('supplier_score_snapshots');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('supplier_addresses');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
    }
};
