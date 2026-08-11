<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfMissing('products', 'pack_size', fn (Blueprint $table) => $table->decimal('pack_size', 14, 3)->nullable()->after('purchase_price'));
        $this->addColumnIfMissing('products', 'track_batches', fn (Blueprint $table) => $table->boolean('track_batches')->default(false)->after('track_inventory'));
        $this->addColumnIfMissing('products', 'track_serials', fn (Blueprint $table) => $table->boolean('track_serials')->default(false)->after('track_batches'));
        $this->addColumnIfMissing('products', 'track_expiry', fn (Blueprint $table) => $table->boolean('track_expiry')->default(false)->after('track_serials'));

        $this->addColumnIfMissing('stock_levels', 'quantity_damaged', fn (Blueprint $table) => $table->decimal('quantity_damaged', 14, 3)->default(0)->after('quantity_reserved'));
        $this->addIndexIfMissing('stock_levels', 'stock_level_company_branch_product_idx', ['company_id', 'branch_id', 'product_id']);

        $this->addColumnIfMissing('stock_adjustments', 'adjustment_type', fn (Blueprint $table) => $table->string('adjustment_type', 40)->default('other')->after('status'));
        $this->addColumnIfMissing('stock_adjustments', 'approval_required', fn (Blueprint $table) => $table->boolean('approval_required')->default(true)->after('adjustment_type'));

        $this->addColumnIfMissing('barcode_print_batch_items', 'inventory_batch_id', fn (Blueprint $table) => $table->unsignedBigInteger('inventory_batch_id')->nullable()->after('product_id'));

        $this->addForeignIdIfMissing('pos_registers', 'warehouse_id', 'branch_id', 'warehouses');
        $this->addForeignIdIfMissing('pos_registers', 'stock_location_id', 'warehouse_id', 'stock_locations');
        $this->addIndexIfMissing('pos_registers', 'pos_register_company_warehouse_idx', ['company_id', 'warehouse_id', 'is_active']);
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

        $this->addColumnIfMissing('stock_movements', 'from_stock_state', fn (Blueprint $table) => $table->string('from_stock_state', 32)->nullable()->after('direction'));
        $this->addColumnIfMissing('stock_movements', 'to_stock_state', fn (Blueprint $table) => $table->string('to_stock_state', 32)->nullable()->after('from_stock_state'));
        $this->addColumnIfMissing('stock_movements', 'inventory_batch_id', fn (Blueprint $table) => $table->unsignedBigInteger('inventory_batch_id')->nullable()->after('product_id'));
        $this->addColumnIfMissing('stock_movements', 'inventory_serial_number_id', fn (Blueprint $table) => $table->unsignedBigInteger('inventory_serial_number_id')->nullable()->after('inventory_batch_id'));
        $this->addIndexIfMissing('stock_movements', 'stock_move_company_state_idx', ['company_id', 'from_stock_state', 'to_stock_state']);

        $this->makeColumnNullableIfNeeded('stock_transfers', 'source_branch_id');
        $this->makeColumnNullableIfNeeded('stock_transfers', 'destination_branch_id');
        $this->addForeignIdIfMissing('stock_transfers', 'source_stock_location_id', 'source_warehouse_id', 'stock_locations');
        $this->addForeignIdIfMissing('stock_transfers', 'destination_stock_location_id', 'destination_warehouse_id', 'stock_locations');
        $this->addColumnIfMissing('stock_transfers', 'idempotency_key', fn (Blueprint $table) => $table->string('idempotency_key', 80)->nullable()->after('transfer_number'));
        $this->addForeignIdIfMissing('stock_transfers', 'approved_by', 'requested_by', 'users');
        $this->addForeignIdIfMissing('stock_transfers', 'packed_by', 'approved_by', 'users');
        $this->addColumnIfMissing('stock_transfers', 'requested_at', fn (Blueprint $table) => $table->timestamp('requested_at')->nullable()->after('submitted_at'));
        $this->addColumnIfMissing('stock_transfers', 'approved_at', fn (Blueprint $table) => $table->timestamp('approved_at')->nullable()->after('requested_at'));
        $this->addColumnIfMissing('stock_transfers', 'packed_at', fn (Blueprint $table) => $table->timestamp('packed_at')->nullable()->after('approved_at'));
        $this->addColumnIfMissing('stock_transfers', 'expected_arrival_at', fn (Blueprint $table) => $table->timestamp('expected_arrival_at')->nullable()->after('dispatched_at'));
        $this->addForeignIdIfMissing('stock_transfers', 'rejected_by', 'cancelled_by', 'users');
        $this->addColumnIfMissing('stock_transfers', 'rejected_at', fn (Blueprint $table) => $table->timestamp('rejected_at')->nullable()->after('rejected_by'));
        $this->addColumnIfMissing('stock_transfers', 'rejection_reason', fn (Blueprint $table) => $table->text('rejection_reason')->nullable()->after('rejected_at'));
        $this->addColumnIfMissing('stock_transfers', 'cancellation_reason', fn (Blueprint $table) => $table->text('cancellation_reason')->nullable()->after('rejection_reason'));
        $this->addIndexIfMissing('stock_transfers', 'stock_transfer_company_idempotency_uq', ['company_id', 'idempotency_key'], true);
        $this->addIndexIfMissing('stock_transfers', 'stock_transfer_company_status_eta_idx', ['company_id', 'status', 'expected_arrival_at']);

        $this->addForeignIdIfMissing('stock_transfer_items', 'source_stock_location_id', 'product_id', 'stock_locations');
        $this->addForeignIdIfMissing('stock_transfer_items', 'destination_stock_location_id', 'source_stock_location_id', 'stock_locations');
        $this->addColumnIfMissing('stock_transfer_items', 'inventory_batch_id', fn (Blueprint $table) => $table->unsignedBigInteger('inventory_batch_id')->nullable()->after('destination_stock_location_id'));
        $this->addColumnIfMissing('stock_transfer_items', 'approved_quantity', fn (Blueprint $table) => $table->decimal('approved_quantity', 14, 3)->default(0)->after('requested_quantity'));
        $this->addColumnIfMissing('stock_transfer_items', 'packed_quantity', fn (Blueprint $table) => $table->decimal('packed_quantity', 14, 3)->default(0)->after('approved_quantity'));
        $this->addColumnIfMissing('stock_transfer_items', 'in_transit_quantity', fn (Blueprint $table) => $table->decimal('in_transit_quantity', 14, 3)->default(0)->after('dispatched_quantity'));
        $this->addColumnIfMissing('stock_transfer_items', 'damaged_quantity', fn (Blueprint $table) => $table->decimal('damaged_quantity', 14, 3)->default(0)->after('received_quantity'));
        $this->addColumnIfMissing('stock_transfer_items', 'short_quantity', fn (Blueprint $table) => $table->decimal('short_quantity', 14, 3)->default(0)->after('damaged_quantity'));
        $this->addColumnIfMissing('stock_transfer_items', 'rejected_quantity', fn (Blueprint $table) => $table->decimal('rejected_quantity', 14, 3)->default(0)->after('short_quantity'));
        $this->addColumnIfMissing('stock_transfer_items', 'unit_snapshot', fn (Blueprint $table) => $table->string('unit_snapshot', 80)->nullable()->after('rejected_quantity'));
        $this->addColumnIfMissing('stock_transfer_items', 'notes', fn (Blueprint $table) => $table->text('notes')->nullable()->after('unit_snapshot'));

        $this->addForeignIdIfMissing('reorder_rules', 'stock_location_id', 'warehouse_id', 'stock_locations', 'cascade');
        $this->dropIndexIfPresent('reorder_rules', ['company_id', 'warehouse_id', 'product_id'], true);
        $this->addIndexIfMissing('reorder_rules', 'reorder_rule_location_product_uq', ['company_id', 'warehouse_id', 'stock_location_id', 'product_id'], true);

        if (! Schema::hasTable('inventory_transfer_receipts')) {
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
        }

        if (! Schema::hasTable('inventory_transfer_receipt_items')) {
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
        }

        if (! Schema::hasTable('inventory_transfer_discrepancies')) {
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
        }

        if (! Schema::hasTable('inventory_stock_counts')) {
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
        }

        if (! Schema::hasTable('inventory_stock_count_items')) {
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
        }

        if (! Schema::hasTable('inventory_batches')) {
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
        }

        if (! Schema::hasTable('inventory_serial_numbers')) {
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
        }

        if (! Schema::hasTable('inventory_transfer_item_serials')) {
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
    }

    private function addColumnIfMissing(string $tableName, string $columnName, Closure $definition): void
    {
        if (Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($definition): void {
            $definition($table);
        });
    }

    private function addForeignIdIfMissing(
        string $tableName,
        string $columnName,
        string $after,
        string $references,
        string $onDelete = 'set null',
    ): void {
        $this->addColumnIfMissing(
            $tableName,
            $columnName,
            fn (Blueprint $table) => $table->unsignedBigInteger($columnName)->nullable()->after($after),
        );

        if (Schema::hasForeignKey($tableName, [$columnName])) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName, $references, $onDelete): void {
            $foreign = $table->foreign($columnName)->references('id')->on($references);

            match ($onDelete) {
                'cascade' => $foreign->cascadeOnDelete(),
                'restrict' => $foreign->restrictOnDelete(),
                default => $foreign->nullOnDelete(),
            };
        });
    }

    /** @param array<int, string> $columns */
    private function addIndexIfMissing(string $tableName, string $indexName, array $columns, bool $unique = false): void
    {
        if (Schema::hasIndex($tableName, $indexName)
            || Schema::hasIndex($tableName, $columns, $unique ? 'unique' : null)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName, $columns, $unique): void {
            $unique ? $table->unique($columns, $indexName) : $table->index($columns, $indexName);
        });
    }

    /** @param array<int, string> $columns */
    private function dropIndexIfPresent(string $tableName, array $columns, bool $unique = false): void
    {
        $index = collect(Schema::getIndexes($tableName))->first(
            fn (array $index): bool => ($index['columns'] ?? []) === $columns
                && (! $unique || ($index['unique'] ?? false)),
        );

        if (! $index || empty($index['name'])) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($index, $unique): void {
            $unique ? $table->dropUnique($index['name']) : $table->dropIndex($index['name']);
        });
    }

    private function makeColumnNullableIfNeeded(string $tableName, string $columnName): void
    {
        $column = collect(Schema::getColumns($tableName))->firstWhere('name', $columnName);

        if (! $column || ($column['nullable'] ?? false)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName): void {
            $table->unsignedBigInteger($columnName)->nullable()->change();
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
            // pack_size may predate Phase R in deployed databases and must remain intact.
            $table->dropColumn(['track_batches', 'track_serials', 'track_expiry']);
        });
    }
};
