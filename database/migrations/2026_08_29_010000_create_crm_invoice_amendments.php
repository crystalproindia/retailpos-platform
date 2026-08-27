<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_invoice_amendments')) {
            Schema::create('crm_invoice_amendments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->foreignId('invoice_id')->constrained('crm_invoices')->restrictOnDelete();
                $table->unsignedInteger('version_from');
                $table->unsignedInteger('version_to');
                $table->text('reason');
                $table->decimal('amount_before', 14, 2);
                $table->decimal('subtotal_added', 14, 2)->default(0);
                $table->decimal('discount_added', 14, 2)->default(0);
                $table->decimal('taxable_added', 14, 2)->default(0);
                $table->decimal('tax_added', 14, 2)->default(0);
                $table->decimal('cgst_added', 14, 2)->default(0);
                $table->decimal('sgst_added', 14, 2)->default(0);
                $table->decimal('igst_added', 14, 2)->default(0);
                $table->decimal('cess_added', 14, 2)->default(0);
                $table->decimal('amount_added', 14, 2);
                $table->decimal('amount_after', 14, 2);
                $table->string('idempotency_key', 64);
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('finalized_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('finalized_at');
                $table->timestamps();

                $table->unique(['invoice_id', 'version_to'], 'crm_inv_amend_invoice_version_uq');
                $table->unique(['company_id', 'idempotency_key'], 'crm_inv_amend_company_idem_uq');
                $table->index(['company_id', 'branch_id', 'finalized_at'], 'crm_inv_amend_scope_final_idx');
            });
        }

        if (! Schema::hasColumn('crm_invoices', 'amendment_version')) {
            Schema::table('crm_invoices', function (Blueprint $table): void {
                $table->unsignedInteger('amendment_version')->default(1)->after('return_status');
            });
        }
        if (! Schema::hasColumn('crm_invoices', 'last_amended_at')) {
            Schema::table('crm_invoices', function (Blueprint $table): void {
                $table->timestamp('last_amended_at')->nullable()->after('cancelled_at');
            });
        }

        if (! Schema::hasColumn('crm_invoice_items', 'amendment_id')) {
            Schema::table('crm_invoice_items', function (Blueprint $table): void {
                $table->foreignId('amendment_id')->nullable()->after('invoice_id')->constrained('crm_invoice_amendments')->restrictOnDelete();
                $table->index(['invoice_id', 'amendment_id'], 'crm_inv_item_amendment_idx');
            });
        }

        if (! Schema::hasTable('crm_invoice_amendment_items')) {
            Schema::create('crm_invoice_amendment_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('amendment_id')->constrained('crm_invoice_amendments')->cascadeOnDelete();
                $table->foreignId('invoice_item_id')->constrained('crm_invoice_items')->restrictOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->string('name_snapshot');
                $table->string('sku_snapshot')->nullable();
                $table->string('hsn_sac_snapshot', 16)->nullable();
                $table->decimal('quantity_snapshot', 12, 3);
                $table->string('unit_snapshot', 32);
                $table->decimal('unit_price_snapshot', 14, 2);
                $table->decimal('discount_snapshot', 14, 2)->default(0);
                $table->decimal('taxable_snapshot', 14, 2);
                $table->decimal('tax_snapshot', 14, 2)->default(0);
                $table->decimal('line_total_snapshot', 14, 2);
                $table->string('cost_status_snapshot', 24)->default('unavailable');
                $table->decimal('unit_cost_snapshot', 14, 2)->nullable();
                $table->timestamps();

                $table->unique('invoice_item_id', 'crm_inv_amend_item_line_uq');
                $table->index(['amendment_id', 'id'], 'crm_inv_amend_item_order_idx');
            });
        }

        if (! Schema::hasColumn('stock_movements', 'crm_invoice_item_id')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->foreignId('crm_invoice_item_id')->nullable()->after('crm_invoice_return_item_id')->constrained('crm_invoice_items')->nullOnDelete();
                $table->unique('crm_invoice_item_id', 'stock_move_crm_inv_item_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropUnique('stock_move_crm_inv_item_uq');
            $table->dropConstrainedForeignId('crm_invoice_item_id');
        });
        Schema::dropIfExists('crm_invoice_amendment_items');
        Schema::table('crm_invoice_items', function (Blueprint $table): void {
            $table->dropIndex('crm_inv_item_amendment_idx');
            $table->dropConstrainedForeignId('amendment_id');
        });
        Schema::table('crm_invoices', function (Blueprint $table): void {
            $table->dropColumn(['amendment_version', 'last_amended_at']);
        });
        Schema::dropIfExists('crm_invoice_amendments');
    }
};
