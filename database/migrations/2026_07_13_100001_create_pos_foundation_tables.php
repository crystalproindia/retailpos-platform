<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('sale_number');
            $table->string('status')->default('held');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('change_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('device_type')->default('desktop');
            $table->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'sale_number']);
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['company_id', 'customer_id', 'completed_at']);
        });

        Schema::create('pos_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index(['company_id', 'product_id']);
            $table->index(['pos_sale_id', 'product_id']);
        });

        Schema::create('pos_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method');
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'payment_method']);
        });

        Schema::create('customer_product_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->unsignedInteger('purchase_count')->default(0);
            $table->decimal('quantity_purchased', 14, 3)->default(0);
            $table->decimal('total_spent', 14, 2)->default(0);
            $table->timestamp('first_purchased_at')->nullable();
            $table->timestamp('last_purchased_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);
            $table->index(['company_id', 'customer_id', 'last_purchased_at'], 'cust_prod_summary_company_customer_last_idx');
        });

        Schema::create('pos_product_pair_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('co_purchase_count')->default(0);
            $table->timestamp('last_purchased_together_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'related_product_id'], 'pos_pair_summary_company_product_related_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_product_pair_summaries');
        Schema::dropIfExists('customer_product_summaries');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
    }
};
