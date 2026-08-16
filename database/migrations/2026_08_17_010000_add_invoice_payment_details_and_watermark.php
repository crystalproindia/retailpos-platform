<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $settingColumns = [
        'account_holder_name' => 'string',
        'bank_name' => 'string',
        'account_number' => 'text',
        'ifsc_code' => 'string',
        'bank_branch_name' => 'string',
        'swift_bic' => 'string',
        'upi_id' => 'string',
        'payment_url' => 'string',
        'payment_note' => 'text',
        'watermark_path' => 'string',
        'watermark_enabled' => 'boolean',
    ];

    /** @var list<string> */
    private array $documentTables = [
        'crm_invoices',
        'crm_quotations',
        'crm_proforma_invoices',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('invoice_template_settings')) {
            throw new RuntimeException('The invoice template settings table is required.');
        }

        foreach ($this->settingColumns as $column => $type) {
            if (Schema::hasColumn('invoice_template_settings', $column)) {
                continue;
            }

            Schema::table('invoice_template_settings', function (Blueprint $table) use ($column, $type): void {
                match ($type) {
                    'text' => $table->text($column)->nullable(),
                    'boolean' => $table->boolean($column)->default(false),
                    default => $table->string($column, $column === 'payment_url' ? 2048 : 255)->nullable(),
                };
            });
        }

        foreach ($this->documentTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                throw new RuntimeException("The {$tableName} table is required.");
            }

            foreach (['payment_details_snapshot', 'watermark_path_snapshot', 'presentation_snapshot_at'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($column): void {
                    match ($column) {
                        'payment_details_snapshot' => $table->text($column)->nullable(),
                        'presentation_snapshot_at' => $table->timestamp($column)->nullable(),
                        default => $table->string($column)->nullable(),
                    };
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->documentTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columns = collect(['payment_details_snapshot', 'watermark_path_snapshot', 'presentation_snapshot_at'])
                ->filter(fn (string $column): bool => Schema::hasColumn($tableName, $column))
                ->all();

            if ($columns !== []) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        if (! Schema::hasTable('invoice_template_settings')) {
            return;
        }

        $columns = collect(array_keys($this->settingColumns))
            ->filter(fn (string $column): bool => Schema::hasColumn('invoice_template_settings', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('invoice_template_settings', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
