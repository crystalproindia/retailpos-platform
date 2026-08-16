<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\InvoiceTemplateSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoicePresentationMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_resumes_from_partial_schema_without_losing_existing_records(): void
    {
        $migration = require database_path('migrations/2026_08_17_010000_add_invoice_payment_details_and_watermark.php');
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $setting = InvoiceTemplateSetting::query()->create(['company_id' => $company->id, 'template_key' => 'structured_gst_grid']);
        $invoice = CrmInvoice::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_number' => 'BASELINE-001',
            'currency' => 'INR',
            'status' => InvoiceStatus::Issued,
            'grand_total' => 100,
            'balance_due' => 100,
        ]);

        $migration->down();
        Schema::table('invoice_template_settings', function (Blueprint $table): void {
            $table->string('account_holder_name')->nullable();
        });
        Schema::table('crm_invoices', function (Blueprint $table): void {
            $table->text('payment_details_snapshot')->nullable();
        });
        InvoiceTemplateSetting::query()->whereKey($setting->id)->update(['account_holder_name' => 'Preserved Account']);
        CrmInvoice::query()->whereKey($invoice->id)->update(['payment_details_snapshot' => json_encode(['account_holder_name' => 'Historical Account'])]);

        $migration->up();
        $migration->up();

        foreach (['account_holder_name', 'bank_name', 'account_number', 'ifsc_code', 'bank_branch_name', 'swift_bic', 'upi_id', 'payment_url', 'payment_note', 'watermark_path', 'watermark_enabled'] as $column) {
            $this->assertTrue(Schema::hasColumn('invoice_template_settings', $column), $column.' is missing.');
        }
        foreach (['crm_invoices', 'crm_quotations', 'crm_proforma_invoices'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'payment_details_snapshot'));
            $this->assertTrue(Schema::hasColumn($table, 'watermark_path_snapshot'));
            $this->assertTrue(Schema::hasColumn($table, 'presentation_snapshot_at'));
        }
        $this->assertDatabaseHas('invoice_template_settings', ['id' => $setting->id, 'account_holder_name' => 'Preserved Account']);
        $this->assertSame(
            ['account_holder_name' => 'Historical Account'],
            json_decode((string) CrmInvoice::query()->findOrFail($invoice->id)->getRawOriginal('payment_details_snapshot'), true),
        );
        $this->assertDatabaseHas('crm_invoices', ['id' => $invoice->id, 'invoice_number' => 'BASELINE-001']);
    }

    public function test_migration_rolls_back_and_reapplies_without_dropping_baseline_records(): void
    {
        $migration = require database_path('migrations/2026_08_17_010000_add_invoice_payment_details_and_watermark.php');
        $company = Company::factory()->create();
        $setting = InvoiceTemplateSetting::query()->create(['company_id' => $company->id, 'template_key' => 'structured_gst_grid']);

        $migration->down();
        $this->assertFalse(Schema::hasColumn('invoice_template_settings', 'watermark_path'));
        $this->assertDatabaseHas('invoice_template_settings', ['id' => $setting->id, 'company_id' => $company->id]);

        $migration->up();
        $this->assertTrue(Schema::hasColumn('invoice_template_settings', 'watermark_path'));
        $this->assertTrue(Schema::hasColumn('crm_invoices', 'presentation_snapshot_at'));
        $this->assertDatabaseHas('invoice_template_settings', ['id' => $setting->id, 'company_id' => $company->id]);
    }
}
