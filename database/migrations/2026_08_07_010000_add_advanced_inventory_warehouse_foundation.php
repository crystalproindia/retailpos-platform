<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('pack_size', 14, 3)->nullable()->after('purchase_price');
            $table->boolean('track_batches')->default(false)->after('track_inventory');
            $table->boolean('track_serials')->default(false)->after('track_batches');
            $table->boolean('track_expiry')->default(false)->after('track_serials');
        });

        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->decimal('quantity_damaged', 14, 3)->default(0)->after('quantity_reserved');
            $table->index(['company_id', 'branch_id', 'product_id'], 'stock_level_company_branch_product_idx');
        });

        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->string('adjustment_type', 40)->default('other')->after('status');
            $table->boolean('approval_required')->default(true)->after('adjustment_type');
        });

        Schema::table('barcode_print_batch_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('inventory_batch_id')->nullable()->after('product_id');
        });

        Schema::table('pos_registers', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('stock_location_id')->nullable()->after('warehouse_id')->constrained('stock_locations')->nullOnDelete();
            $table->index(['company_id', 'warehouse_id', 'is_active'], 'pos_register_company_warehouse_idx');
        });
        DB::table('pos_registers')
            ->select(['company_id', 'branch_id'])
            ->distinct()
            ->orderBy('company_id')
            ->orderBy('branch_id')
            ->each(function (object $registerScope): void {
                $warehouseId = DB::table('warehouses')
                    ->where('company_id', $registerScope->company_id)
                    ->where('branch_id', $registerScope->branch_id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('is_primary')
                    ->orderBy('id')
                    ->value('id');

                if (! $warehouseId) {
                    $branch = DB::table('branches')
                        ->where('company_id', $registerScope->company_id)
                        ->where('id', $registerScope->branch_id)
                        ->first(['name', 'country']);
                    $baseCode = 'POS-'.$registerScope->branch_id;
                    $code = $baseCode;
                    $suffix = 1;

                    while (DB::table('warehouses')->where('company_id', $registerScope->company_id)->where('code', $code)->exists()) {
                        $code = $baseCode.'-'.$suffix++;
                    }

                    $warehouseId = DB::table('warehouses')->insertGetId([
                        'company_id' => $registerScope->company_id,
                        'branch_id' => $registerScope->branch_id,
                        'name' => ($branch?->name ?? 'Outlet').' Stock',
                        'code' => $code,
                        'type' => 'store',
                        'country' => $branch?->country ?? 'India',
                        'is_primary' => true,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('pos_registers')
                    ->where('company_id', $registerScope->company_id)
                    ->where('branch_id', $registerScope->branch_id)
                    ->whereNull('warehouse_id')
                    ->update(['warehouse_id' => $warehouseId]);
            });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->string('from_stock_state', 32)->nullable()->after('direction');
            $table->string('to_stock_state', 32)->nullable()->after('from_stock_state');
            $table->unsignedBigInteger('inventory_batch_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('inventory_serial_number_id')->nullable()->after('inventory_batch_id');
            $table->index(['company_id', 'from_stock_state', 'to_stock_state'], 'stock_move_company_state_idx');
        });

        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_branch_id')->nullable()->change();
            $table->unsignedBigInteger('destination_branch_id')->nullable()->change();
            $table->foreignId('source_stock_location_id')->nullable()->after('source_warehouse_id')->constrained('stock_locations')->nullOnDelete();
            $table->foreignId('destination_stock_location_id')->nullable()->after('destination_warehouse_id')->constrained('stock_locations')->nullOnDelete();
            $table->string('idempotency_key', 80)->nullable()->after('transfer_number');
            $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->foreignId('packed_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable()->after('submitted_at');
            $table->timestamp('approved_at')->nullable()->after('requested_at');
            $table->timestamp('packed_at')->nullable()->after('approved_at');
            $table->timestamp('expected_arrival_at')->nullable()->after('dispatched_at');
            $table->foreignId('rejected_by')->nullable()->after('cancelled_by')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->text('cancellation_reason')->nullable()->after('rejection_reason');
            $table->unique(['company_id', 'idempotency_key'], 'stock_transfer_company_idempotency_uq');
            $table->index(['company_id', 'status', 'expected_arrival_at'], 'stock_transfer_company_status_eta_idx');
        });

        Schema::table('stock_transfer_items', function (Blueprint $table): void {
            $table->foreignId('source_stock_location_id')->nullable()->after('product_id')->constrained('stock_locations')->nullOnDelete();
            $table->foreignId('destination_stock_location_id')->nullable()->after('source_stock_location_id')->constrained('stock_locations')->nullOnDelete();
            $table->unsignedBigInteger('inventory_batch_id')->nullable()->after('destination_stock_location_id');
            $table->decimal('approved_quantity', 14, 3)->default(0)->after('requested_quantity');
            $table->decimal('packed_quantity', 14, 3)->default(0)->after('approved_quantity');
            $table->decimal('in_transit_quantity', 14, 3)->default(0)->after('dispatched_quantity');
            $table->decimal('damaged_quantity', 14, 3)->default(0)->after('received_quantity');
            $table->decimal('short_quantity', 14, 3)->default(0)->after('damaged_quantity');
            $table->decimal('rejected_quantity', 14, 3)->default(0)->after('short_quantity');
            $table->string('unit_snapshot', 80)->nullable()->after('rejected_quantity');
            $table->text('notes')->nullable()->after('unit_snapshot');
        });

        Schema::table('reorder_rules', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'warehouse_id', 'product_id']);
            $table->foreignId('stock_location_id')->nullable()->after('warehouse_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->unique(['company_id', 'warehouse_id', 'stock_location_id', 'product_id'], 'reorder_rule_location_product_uq');
        });

        Schema::create('inventory_transfer_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->string('receipt_number', 48);
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 80)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'receipt_number'], 'transfer_receipt_company_number_uq');
            $table->unique(['company_id', 'idempotency_key'], 'transfer_receipt_company_idempotency_uq');
        });

        Schema::create('inventory_transfer_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_transfer_receipt_id');
            $table->foreign('inventory_transfer_receipt_id', 'transfer_receipt_item_receipt_fk')->references('id')->on('inventory_transfer_receipts')->cascadeOnDelete();
            $table->foreignId('stock_transfer_item_id')->constrained('stock_transfer_items')->restrictOnDelete();
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->decimal('damaged_quantity', 14, 3)->default(0);
            $table->decimal('short_quantity', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['inventory_transfer_receipt_id', 'stock_transfer_item_id'], 'transfer_receipt_item_line_uq');
        });

        Schema::create('inventory_transfer_discrepancies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('stock_transfer_item_id')->constrained('stock_transfer_items')->cascadeOnDelete();
            $table->foreignId('inventory_transfer_receipt_id')->nullable();
            $table->foreign('inventory_transfer_receipt_id', 'transfer_discrepancy_receipt_fk')->references('id')->on('inventory_transfer_receipts')->nullOnDelete();
            $table->string('type', 40);
            $table->string('reason', 255)->nullable();
            $table->decimal('expected_quantity', 14, 3);
            $table->decimal('actual_quantity', 14, 3);
            $table->decimal('discrepancy_quantity', 14, 3);
            $table->string('status', 24)->default('open');
            $table->string('resolution', 40)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'type'], 'transfer_discrepancy_company_status_idx');
        });

        Schema::create('inventory_stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->string('count_number', 48);
            $table->string('type', 32)->default('full');
            $table->string('status', 24)->default('draft');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'count_number'], 'stock_count_company_number_uq');
            $table->index(['company_id', 'warehouse_id', 'status'], 'stock_count_company_warehouse_status_idx');
        });

        Schema::create('inventory_stock_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_stock_count_id')->constrained('inventory_stock_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->decimal('system_quantity', 14, 3);
            $table->decimal('counted_quantity', 14, 3)->nullable();
            $table->decimal('variance_quantity', 14, 3)->nullable();
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['inventory_stock_count_id', 'product_id', 'stock_location_id'], 'stock_count_item_scope_uq');
        });

        Schema::create('inventory_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->string('batch_number', 120);
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->decimal('quantity_available', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->string('supplier_reference', 120)->nullable();
            $table->string('receipt_reference', 120)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'product_id', 'warehouse_id', 'stock_location_id', 'batch_number'], 'inventory_batch_scope_number_uq');
            $table->index(['company_id', 'expires_at', 'status'], 'inventory_batch_company_expiry_idx');
        });

        Schema::create('inventory_serial_numbers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->string('serial_number', 160);
            $table->string('status', 24)->default('available');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'product_id', 'serial_number'], 'inventory_serial_company_product_uq');
            $table->index(['company_id', 'warehouse_id', 'status'], 'inventory_serial_company_location_idx');
        });

        Schema::create('inventory_transfer_item_serials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_item_id')->constrained('stock_transfer_items')->cascadeOnDelete();
            $table->foreignId('inventory_serial_number_id');
            $table->foreign('inventory_serial_number_id', 'transfer_item_serial_number_fk')->references('id')->on('inventory_serial_numbers')->restrictOnDelete();
            $table->string('status', 24)->default('reserved');
            $table->timestamps();
            $table->unique('inventory_serial_number_id', 'transfer_item_serial_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_item_serials');
        Schema::dropIfExists('inventory_serial_numbers');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('inventory_stock_count_items');
        Schema::dropIfExists('inventory_stock_counts');
        Schema::dropIfExists('inventory_transfer_discrepancies');
        Schema::dropIfExists('inventory_transfer_receipt_items');
        Schema::dropIfExists('inventory_transfer_receipts');

        Schema::table('reorder_rules', function (Blueprint $table): void {
            $table->dropUnique('reorder_rule_location_product_uq');
            $table->dropConstrainedForeignId('stock_location_id');
            $table->unique(['company_id', 'warehouse_id', 'product_id']);
        });

        Schema::table('stock_transfer_items', function (Blueprint $table): void {
            $table->dropColumn('inventory_batch_id');
            $table->dropConstrainedForeignId('destination_stock_location_id');
            $table->dropConstrainedForeignId('source_stock_location_id');
            $table->dropColumn(['approved_quantity', 'packed_quantity', 'in_transit_quantity', 'damaged_quantity', 'short_quantity', 'rejected_quantity', 'unit_snapshot', 'notes']);
        });

        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->dropUnique('stock_transfer_company_idempotency_uq');
            $table->dropIndex('stock_transfer_company_status_eta_idx');
            $table->dropConstrainedForeignId('source_stock_location_id');
            $table->dropConstrainedForeignId('destination_stock_location_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('packed_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['idempotency_key', 'requested_at', 'approved_at', 'packed_at', 'expected_arrival_at', 'rejected_at', 'rejection_reason', 'cancellation_reason']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_move_company_state_idx');
            $table->dropColumn(['inventory_batch_id', 'inventory_serial_number_id']);
            $table->dropColumn(['from_stock_state', 'to_stock_state']);
        });

        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->dropIndex('stock_level_company_branch_product_idx');
            $table->dropColumn('quantity_damaged');
        });

        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->dropColumn(['adjustment_type', 'approval_required']);
        });

        Schema::table('barcode_print_batch_items', function (Blueprint $table): void {
            $table->dropColumn('inventory_batch_id');
        });

        Schema::table('pos_registers', function (Blueprint $table): void {
            $table->dropIndex('pos_register_company_warehouse_idx');
            $table->dropConstrainedForeignId('stock_location_id');
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['pack_size', 'track_batches', 'track_serials', 'track_expiry']);
        });
    }
};
