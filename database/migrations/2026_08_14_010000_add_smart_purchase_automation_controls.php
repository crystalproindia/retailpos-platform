<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumn('purchase_requests', 'submitted_at', fn (Blueprint $table) => $table->timestamp('submitted_at')->nullable()->after('reviewed_at'));
        $this->addColumn('purchase_requests', 'cancelled_by', fn (Blueprint $table) => $table->foreignId('cancelled_by')->nullable()->after('reviewed_by'));
        $this->addColumn('purchase_requests', 'cancelled_at', fn (Blueprint $table) => $table->timestamp('cancelled_at')->nullable()->after('submitted_at'));
        $this->addIndex('purchase_requests', 'purch_req_company_branch_status_idx', ['company_id', 'branch_id', 'status']);

        $this->addColumn('purchase_request_items', 'converted_quantity', fn (Blueprint $table) => $table->decimal('converted_quantity', 14, 3)->default(0)->after('approved_quantity'));
        $this->addColumn('purchase_request_items', 'approval_notes', fn (Blueprint $table) => $table->text('approval_notes')->nullable()->after('notes'));

        $this->addColumn('purchase_orders', 'supplier_confirmed_at', fn (Blueprint $table) => $table->timestamp('supplier_confirmed_at')->nullable()->after('sent_at'));
        $this->addColumn('purchase_orders', 'supplier_confirmation_reference', fn (Blueprint $table) => $table->string('supplier_confirmation_reference', 120)->nullable()->after('supplier_confirmed_at'));
        $this->addIndex('purchase_orders', 'purch_order_company_warehouse_status_idx', ['company_id', 'warehouse_id', 'status']);

        $this->addColumn('goods_receipts', 'idempotency_key', fn (Blueprint $table) => $table->string('idempotency_key', 80)->nullable()->after('grn_number'));
        $this->addColumn('goods_receipts', 'posted_by', fn (Blueprint $table) => $table->foreignId('posted_by')->nullable()->after('checked_by'));
        $this->addColumn('goods_receipts', 'posted_at', fn (Blueprint $table) => $table->timestamp('posted_at')->nullable()->after('checked_at'));
        $this->addIndex('goods_receipts', 'grn_company_idempotency_uq', ['company_id', 'idempotency_key'], true);
        $this->addIndex('goods_receipts', 'grn_company_warehouse_status_idx', ['company_id', 'warehouse_id', 'status']);

        $this->addColumn('goods_receipt_items', 'damaged_quantity', fn (Blueprint $table) => $table->decimal('damaged_quantity', 14, 3)->default(0)->after('rejected_quantity'));
        $this->addColumn('goods_receipt_items', 'short_quantity', fn (Blueprint $table) => $table->decimal('short_quantity', 14, 3)->default(0)->after('damaged_quantity'));
        $this->addColumn('goods_receipt_items', 'inventory_batch_id', fn (Blueprint $table) => $table->foreignId('inventory_batch_id')->nullable()->after('stock_location_id'));

        $this->ensureForeign('purchase_requests', 'cancelled_by', 'users', 'phase_s_pr_cancelled_by_fk');
        $this->ensureForeign('goods_receipts', 'posted_by', 'users', 'phase_s_grn_posted_by_fk');
        $this->ensureForeign('goods_receipt_items', 'inventory_batch_id', 'inventory_batches', 'phase_s_grni_batch_fk');

        if (! Schema::hasTable('goods_receipt_item_serials')) {
            Schema::create('goods_receipt_item_serials', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('goods_receipt_item_id');
                $table->string('serial_number', 160);
                $table->timestamps();
                $table->unique(['goods_receipt_item_id', 'serial_number'], 'grn_item_serial_unique');
            });
        }

        $this->ensureForeign('goods_receipt_item_serials', 'goods_receipt_item_id', 'goods_receipt_items', 'phase_s_grnis_item_fk', 'cascade');

        $this->addColumn('purchase_invoices', 'match_status', fn (Blueprint $table) => $table->string('match_status', 24)->default('pending')->after('status'));
        $this->addColumn('purchase_invoices', 'matched_at', fn (Blueprint $table) => $table->timestamp('matched_at')->nullable()->after('verified_at'));
        $this->addColumn('purchase_invoices', 'match_reviewed_by', fn (Blueprint $table) => $table->foreignId('match_reviewed_by')->nullable()->after('verified_by'));
        $this->addColumn('purchase_invoices', 'match_reviewed_at', fn (Blueprint $table) => $table->timestamp('match_reviewed_at')->nullable()->after('matched_at'));
        $this->addIndex('purchase_invoices', 'purch_invoice_company_match_status_idx', ['company_id', 'match_status']);
        $this->ensureForeign('purchase_invoices', 'match_reviewed_by', 'users', 'phase_s_pi_match_reviewer_fk');

        if (! Schema::hasTable('purchase_invoice_match_exceptions')) {
            Schema::create('purchase_invoice_match_exceptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->foreignId('purchase_invoice_id');
                $table->foreignId('purchase_order_id')->nullable();
                $table->foreignId('goods_receipt_item_id')->nullable();
                $table->foreignId('purchase_invoice_item_id')->nullable();
                $table->string('type', 48);
                $table->string('status', 24)->default('open');
                $table->decimal('expected_quantity', 14, 3)->nullable();
                $table->decimal('actual_quantity', 14, 3)->nullable();
                $table->decimal('expected_amount', 14, 2)->nullable();
                $table->decimal('actual_amount', 14, 2)->nullable();
                $table->text('details')->nullable();
                $table->foreignId('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'status', 'type'], 'purch_inv_match_company_status_idx');
                $table->index(['purchase_invoice_id', 'status'], 'purch_inv_match_invoice_status_idx');
            });
        }

        $this->ensureForeign('purchase_invoice_match_exceptions', 'company_id', 'companies', 'phase_s_pime_company_fk', 'cascade');
        $this->ensureForeign('purchase_invoice_match_exceptions', 'purchase_invoice_id', 'purchase_invoices', 'phase_s_pime_invoice_fk', 'cascade');
        $this->ensureForeign('purchase_invoice_match_exceptions', 'purchase_order_id', 'purchase_orders', 'phase_s_pime_order_fk');
        $this->ensureForeign('purchase_invoice_match_exceptions', 'goods_receipt_item_id', 'goods_receipt_items', 'phase_s_pime_grn_item_fk');
        $this->ensureForeign('purchase_invoice_match_exceptions', 'purchase_invoice_item_id', 'purchase_invoice_items', 'pime_invoice_item_fk');
        $this->ensureForeign('purchase_invoice_match_exceptions', 'resolved_by', 'users', 'phase_s_pime_resolved_by_fk');

        $this->addIndex('purchase_invoice_match_exceptions', 'purch_inv_match_company_status_idx', ['company_id', 'status', 'type']);
        $this->addIndex('purchase_invoice_match_exceptions', 'purch_inv_match_invoice_status_idx', ['purchase_invoice_id', 'status']);
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_match_exceptions');
        Schema::dropIfExists('goods_receipt_item_serials');
        // Phase S additions are intentionally retained on rollback to preserve historical purchasing evidence.
    }

    private function addColumn(string $table, string $column, Closure $definition): void
    {
        $this->requireTable($table);

        if (! Schema::hasColumn($table, $column)) {
            Schema::table($table, fn (Blueprint $blueprint) => $definition($blueprint));
        }
    }

    /** @param array<int, string> $columns */
    private function addIndex(string $table, string $name, array $columns, bool $unique = false): void
    {
        $this->requireTable($table);

        if (Schema::hasIndex($table, $name, $unique ? 'unique' : null)
            || Schema::hasIndex($table, $columns, $unique ? 'unique' : null)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name, $unique): void {
            $unique ? $blueprint->unique($columns, $name) : $blueprint->index($columns, $name);
        });
    }

    private function ensureForeign(
        string $table,
        string $column,
        string $referenceTable,
        string $name,
        string $onDelete = 'null',
    ): void {
        $this->requireTable($table);
        $this->requireTable($referenceTable);

        if (! Schema::hasColumn($table, $column) || ! Schema::hasColumn($referenceTable, 'id')) {
            throw new RuntimeException("Cannot create {$name}: required columns are missing.");
        }

        $foreign = collect(Schema::getForeignKeys($table))->first(
            fn (array $key): bool => ($key['columns'] ?? []) === [$column],
        );

        if ($foreign) {
            $compatible = ($foreign['foreign_table'] ?? null) === $referenceTable
                && ($foreign['foreign_columns'] ?? []) === ['id']
                && $this->normalizeDeleteAction($foreign['on_delete'] ?? null) === $onDelete;

            if (! $compatible) {
                throw new RuntimeException("Existing foreign key on {$table}.{$column} is incompatible with {$name}.");
            }

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referenceTable, $name, $onDelete): void {
            $foreign = $blueprint->foreign($column, $name)->references('id')->on($referenceTable);

            match ($onDelete) {
                'cascade' => $foreign->cascadeOnDelete(),
                'restrict' => $foreign->restrictOnDelete(),
                default => $foreign->nullOnDelete(),
            };
        });
    }

    private function requireTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException("Required table {$table} does not exist.");
        }
    }

    private function normalizeDeleteAction(?string $action): string
    {
        return match (strtolower((string) $action)) {
            'cascade' => 'cascade',
            'restrict', 'no action' => 'restrict',
            default => 'null',
        };
    }
};
