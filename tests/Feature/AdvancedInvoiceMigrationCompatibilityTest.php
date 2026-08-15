<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\SalesDocumentSetting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdvancedInvoiceMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_advanced_invoice_migration_resumes_without_losing_settings_or_history(): void
    {
        $migrationPath = database_path('migrations/2026_08_15_010000_add_advanced_invoice_customization_foundation.php');
        $migrationName = pathinfo($migrationPath, PATHINFO_FILENAME);
        $migration = require $migrationPath;
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $administrator = User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => UserRole::Administrator,
        ]);
        $setting = SalesDocumentSetting::query()->create([
            'company_id' => $company->id,
            'invoice_prefix' => 'CPRO',
            'quotation_prefix' => 'CPRO',
            'proforma_prefix' => 'CPRO',
            'updated_by' => $administrator->id,
        ]);
        $invoice = CrmInvoice::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_number' => 'RPOS-INV-2026-00001',
            'currency' => 'INR',
            'tax_mode' => 'gst',
            'tax_total' => '18.00',
            'grand_total' => '118.00',
            'balance_due' => '118.00',
        ]);

        Schema::table('sales_document_settings', function (Blueprint $table): void {
            $table->dropForeign(['updated_by']);
        });

        $migration->up();
        $migration->up();
        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('sales_document_settings', [
            'id' => $setting->id,
            'company_id' => $company->id,
            'invoice_prefix' => 'CPRO',
            'quotation_prefix' => 'CPRO',
            'proforma_prefix' => 'CPRO',
        ]);
        $this->assertDatabaseHas('crm_invoices', [
            'id' => $invoice->id,
            'invoice_number' => 'RPOS-INV-2026-00001',
            'tax_mode' => 'gst',
            'tax_total' => 18,
            'grand_total' => 118,
        ]);
        $this->assertTrue(Schema::hasForeignKey('sales_document_settings', ['company_id']));
        $this->assertTrue(Schema::hasForeignKey('sales_document_settings', ['updated_by']));
        $this->assertTrue(Schema::hasIndex('sales_document_settings', ['company_id'], 'unique'));
        $this->assertCount(1, collect(Schema::getIndexes('sales_document_settings'))->filter(
            fn (array $index): bool => ($index['columns'] ?? []) === ['company_id'] && ($index['unique'] ?? false),
        ));

        DB::table('migrations')->where('migration', $migrationName)->delete();

        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertSame(1, DB::table('migrations')->where('migration', $migrationName)->count());
        $this->assertDatabaseHas('crm_invoices', ['id' => $invoice->id, 'invoice_number' => 'RPOS-INV-2026-00001']);
    }

    public function test_advanced_invoice_identifiers_are_mysql_safe_and_explicit(): void
    {
        $identifiers = [
            'sales_doc_company_fk',
            'sales_doc_updated_by_fk',
            'sales_document_settings_company_uq',
        ];
        $source = file_get_contents(database_path('migrations/2026_08_15_010000_add_advanced_invoice_customization_foundation.php'));

        foreach ($identifiers as $identifier) {
            $this->assertLessThanOrEqual(64, strlen($identifier), $identifier.' exceeds MySQL\'s identifier limit.');
            $this->assertStringContainsString("'{$identifier}'", $source);
        }

        $this->assertStringNotContainsString('sales_document_settings_company_id_foreign', $source);
        $this->assertStringNotContainsString('sales_document_settings_updated_by_foreign', $source);
    }
}
