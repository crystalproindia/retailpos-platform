<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_document_settings')) {
            Schema::create('sales_document_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->string('invoice_prefix', 24)->default('RPOS');
                $table->string('quotation_prefix', 24)->default('RPQ');
                $table->string('proforma_prefix', 24)->default('RPI');
                $table->foreignId('updated_by')->nullable();
                $table->timestamps();
                $table->unique('company_id', 'sales_document_settings_company_uq');
            });
        }

        if (! Schema::hasColumn('sales_document_settings', 'proforma_prefix')) {
            Schema::table('sales_document_settings', function (Blueprint $table): void {
                $table->string('proforma_prefix', 24)->default('RPI')->after('quotation_prefix');
            });
        }

        $this->ensureForeign('sales_document_settings', 'company_id', 'companies', 'sales_doc_company_fk', 'cascade');
        $this->ensureForeign('sales_document_settings', 'updated_by', 'users', 'sales_doc_updated_by_fk');
        $this->ensureUnique('sales_document_settings', ['company_id'], 'sales_document_settings_company_uq');

        $this->requireTable('companies');
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'authorized_signature_path')) {
                $table->string('authorized_signature_path')->nullable()->after('invoice_logo_path');
            }
            if (! Schema::hasColumn('companies', 'authorized_signatory_name')) {
                $table->string('authorized_signatory_name')->nullable()->after('authorized_signature_path');
            }
            if (! Schema::hasColumn('companies', 'authorized_signatory_designation')) {
                $table->string('authorized_signatory_designation')->nullable()->after('authorized_signatory_name');
            }
        });

        $this->requireTable('crm_invoices');
        Schema::table('crm_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_invoices', 'tax_mode')) {
                $table->string('tax_mode', 16)->default('gst')->after('tax_classification');
            }
            if (! Schema::hasColumn('crm_invoices', 'show_authorized_signature')) {
                $table->boolean('show_authorized_signature')->default(true)->after('terms_conditions');
            }
            if (! Schema::hasColumn('crm_invoices', 'signature_path_snapshot')) {
                $table->string('signature_path_snapshot')->nullable()->after('show_authorized_signature');
            }
            if (! Schema::hasColumn('crm_invoices', 'signatory_name_snapshot')) {
                $table->string('signatory_name_snapshot')->nullable()->after('signature_path_snapshot');
            }
            if (! Schema::hasColumn('crm_invoices', 'signatory_designation_snapshot')) {
                $table->string('signatory_designation_snapshot')->nullable()->after('signatory_name_snapshot');
            }
        });

        $this->requireTable('crm_quotations');
        Schema::table('crm_quotations', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_quotations', 'tax_mode')) {
                $table->string('tax_mode', 16)->default('gst')->after('currency');
            }
            if (! Schema::hasColumn('crm_quotations', 'show_authorized_signature')) {
                $table->boolean('show_authorized_signature')->default(true)->after('terms_conditions');
            }
            if (! Schema::hasColumn('crm_quotations', 'signature_path_snapshot')) {
                $table->string('signature_path_snapshot')->nullable()->after('show_authorized_signature');
            }
            if (! Schema::hasColumn('crm_quotations', 'signatory_name_snapshot')) {
                $table->string('signatory_name_snapshot')->nullable()->after('signature_path_snapshot');
            }
            if (! Schema::hasColumn('crm_quotations', 'signatory_designation_snapshot')) {
                $table->string('signatory_designation_snapshot')->nullable()->after('signatory_name_snapshot');
            }
        });

        $this->requireTable('crm_proforma_invoices');
        Schema::table('crm_proforma_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_proforma_invoices', 'tax_mode')) {
                $table->string('tax_mode', 16)->default('gst')->after('currency');
            }
            if (! Schema::hasColumn('crm_proforma_invoices', 'show_authorized_signature')) {
                $table->boolean('show_authorized_signature')->default(true)->after('terms_conditions');
            }
            if (! Schema::hasColumn('crm_proforma_invoices', 'signature_path_snapshot')) {
                $table->string('signature_path_snapshot')->nullable()->after('show_authorized_signature');
            }
            if (! Schema::hasColumn('crm_proforma_invoices', 'signatory_name_snapshot')) {
                $table->string('signatory_name_snapshot')->nullable()->after('signature_path_snapshot');
            }
            if (! Schema::hasColumn('crm_proforma_invoices', 'signatory_designation_snapshot')) {
                $table->string('signatory_designation_snapshot')->nullable()->after('signatory_name_snapshot');
            }
        });
    }

    public function down(): void
    {
        // Forward remediation preserves numbering settings and historical document snapshots.
    }

    /** @param list<string> $columns */
    private function ensureUnique(string $table, array $columns, string $name): void
    {
        $this->requireTable($table);

        if (Schema::hasIndex($table, $columns, 'unique')) {
            return;
        }

        if (Schema::hasIndex($table, $name)) {
            throw new RuntimeException("Existing index {$name} does not provide the required uniqueness.");
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
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
