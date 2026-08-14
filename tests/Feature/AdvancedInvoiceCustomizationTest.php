<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Compliance\GstSetting;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmQuotation;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\User;
use App\Services\Branding\CompanyBrandingService;
use App\Services\Crm\DocumentTaxModeService;
use App\Services\Crm\InvoiceService;
use App\Services\Crm\InvoiceTemplateService;
use App\Services\Crm\SalesDocumentNumberService;
use App\Support\Invoices\InvoiceTemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdvancedInvoiceCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_prefixes_continue_existing_sequences_without_rewriting_history(): void
    {
        $user = $this->manager();
        CrmInvoice::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'invoice_number' => 'RPOS-INV-'.now()->format('Y').'-00007', 'status' => InvoiceStatus::Draft, 'currency' => 'INR', 'grand_total' => 0, 'balance_due' => 0]);
        CrmQuotation::create(['company_id' => $user->company_id, 'lead_id' => $this->leadId($user), 'quotation_number' => 'RPQ-'.now()->format('Y').'-000009', 'title' => 'Historical', 'status' => 'draft', 'currency' => 'INR']);

        $this->actingAs($user)->put(route('sales.invoices.document-settings.update'), [
            'invoice_prefix' => 'cpro',
            'quotation_prefix' => 'cpro',
        ])->assertRedirect();

        $numbers = app(SalesDocumentNumberService::class);
        $invoiceNumber = DB::transaction(fn (): string => $numbers->nextInvoiceNumber($user->company_id));
        $quotationNumber = DB::transaction(fn (): string => $numbers->nextQuotationNumber($user->company_id));
        $year = now()->format('Y');

        $this->assertSame("CPRO-INV-{$year}-00008", $invoiceNumber);
        $this->assertSame("CPRO-QUO-{$year}-00010", $quotationNumber);
        $this->assertDatabaseHas('crm_invoices', ['invoice_number' => "RPOS-INV-{$year}-00007"]);
        $this->assertDatabaseHas('crm_quotations', ['quotation_number' => "RPQ-{$year}-000009"]);
    }

    public function test_no_gst_mode_is_server_calculated_and_allowed_only_for_eligible_companies(): void
    {
        $user = $this->manager();
        GstSetting::create(['company_id' => $user->company_id, 'legal_name' => $user->company->name, 'registration_type' => 'regular']);
        $modes = app(DocumentTaxModeService::class);

        try {
            $modes->normalize($user->company, 'no_gst');
            $this->fail('Regular GST companies must not bypass tax through the document mode.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        GstSetting::query()->where('company_id', $user->company_id)->update(['registration_type' => 'exempt']);
        $calculation = app(InvoiceService::class)->calculate([
            ['name' => 'Exempt service', 'quantity' => '1', 'unit_price' => '100.00', 'discount_value' => '0', 'tax_rate' => '18'],
        ], '0', $modes->normalize($user->company, 'no_gst'));

        $this->assertSame('0.00', $calculation['tax_total']);
        $this->assertSame('100.00', $calculation['grand_total']);
        $this->assertSame('0.000', $calculation['items'][0]['tax_rate']);
        $this->assertSame('0.00', $calculation['items'][0]['tax_amount']);
    }

    public function test_private_signature_is_available_to_the_saved_document_snapshot_only(): void
    {
        Storage::fake('local');
        $user = $this->manager();
        $company = app(CompanyBrandingService::class)->replace($user->company, $user, UploadedFile::fake()->image('signature.png', 320, 120), 'signature');
        $invoice = CrmInvoice::create([
            'company_id' => $company->id, 'branch_id' => $user->branch_id, 'invoice_number' => 'RPOS-INV-SIGNATURE', 'currency' => 'INR',
            'status' => InvoiceStatus::Issued, 'tax_mode' => 'no_gst', 'show_authorized_signature' => true,
            'signature_path_snapshot' => $company->authorized_signature_path, 'signatory_name_snapshot' => 'A. Dangal', 'signatory_designation_snapshot' => 'Director',
            'grand_total' => 100, 'balance_due' => 100,
        ]);
        $invoice->items()->create(['name' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'line_subtotal' => 100, 'line_total' => 100]);

        $render = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));
        $this->assertFalse($render['is_gst']);
        $this->assertStringStartsWith('data:image/png;base64,', $render['signature']['data_uri']);
        $this->assertStringContainsString('A. Dangal', view('invoice-templates.structured-gst-grid', ['invoice' => $invoice, 'render' => $render])->render());
    }

    public function test_template_registry_has_unique_keys_and_a_compact_catalog(): void
    {
        $registry = app(InvoiceTemplateRegistry::class);
        $definitions = $registry->all();

        $this->assertGreaterThanOrEqual(40, count($definitions));
        $this->assertSame(array_keys($definitions), array_unique(array_keys($definitions)));
        foreach ($definitions as $definition) {
            $this->assertContains($definition['paper_format'], InvoiceTemplateRegistry::FORMATS);
            $this->assertTrue(view()->exists($definition['view']));
        }
    }

    public function test_document_numbering_settings_are_tenant_isolated_and_reject_unsafe_prefixes(): void
    {
        $manager = $this->manager();
        $other = $this->manager();

        $this->actingAs($manager)->put(route('sales.invoices.document-settings.update'), [
            'invoice_prefix' => 'SAFE-CO',
            'quotation_prefix' => 'SAFE-CO',
            'proforma_prefix' => 'SAFE-CO',
        ])->assertRedirect();

        $this->actingAs($manager)->from(route('sales.invoices.document-settings.index'))->put(route('sales.invoices.document-settings.update'), [
            'invoice_prefix' => '<script>',
            'quotation_prefix' => 'SAFE-CO',
            'proforma_prefix' => 'SAFE-CO',
        ])->assertRedirect(route('sales.invoices.document-settings.index'))->assertSessionHasErrors('invoice_prefix');

        $numbers = app(SalesDocumentNumberService::class);
        $this->assertStringStartsWith('SAFE-CO-INV-', DB::transaction(fn (): string => $numbers->nextInvoiceNumber($manager->company_id)));
        $this->assertStringStartsWith('RPOS-INV-', DB::transaction(fn (): string => $numbers->nextInvoiceNumber($other->company_id)));
    }

    public function test_staff_cannot_open_or_change_company_document_numbering(): void
    {
        $manager = $this->manager();
        $staff = User::factory()->for($manager->company)->create(['branch_id' => $manager->branch_id, 'role' => UserRole::Staff]);

        $this->actingAs($staff)->get(route('sales.invoices.document-settings.index'))->assertForbidden();
        $this->actingAs($staff)->put(route('sales.invoices.document-settings.update'), [
            'invoice_prefix' => 'STAFF', 'quotation_prefix' => 'STAFF', 'proforma_prefix' => 'STAFF',
        ])->assertForbidden();
    }

    public function test_legacy_and_custom_proforma_sequences_are_preserved_without_rewriting_history(): void
    {
        $user = $this->manager();
        $year = now()->format('Y');
        CrmProformaInvoice::create([
            'company_id' => $user->company_id,
            'proforma_number' => "RPI-{$year}-000007",
            'title' => 'Historical proforma',
            'currency' => 'INR',
            'invoice_date' => today(),
            'grand_total' => 0,
            'balance_amount' => 0,
        ]);

        $numbers = app(SalesDocumentNumberService::class);
        $this->assertSame("RPI-{$year}-000008", DB::transaction(fn (): string => $numbers->nextProformaNumber($user->company_id)));
        $this->actingAs($user)->put(route('sales.invoices.document-settings.update'), [
            'invoice_prefix' => 'RPOS', 'quotation_prefix' => 'RPQ', 'proforma_prefix' => 'CPRO',
        ])->assertRedirect();
        $this->assertSame("CPRO-PI-{$year}-00008", DB::transaction(fn (): string => $numbers->nextProformaNumber($user->company_id)));
        $this->assertDatabaseHas('crm_proforma_invoices', ['proforma_number' => "RPI-{$year}-000007"]);
    }

    public function test_no_gst_invoice_output_uses_an_intentional_non_tax_layout(): void
    {
        $user = $this->manager();
        $invoice = CrmInvoice::create([
            'company_id' => $user->company_id, 'branch_id' => $user->branch_id,
            'invoice_number' => 'RPOS-NON-GST-OUTPUT', 'currency' => 'INR', 'status' => InvoiceStatus::Issued,
            'tax_mode' => 'no_gst', 'grand_total' => 100, 'balance_due' => 100,
        ]);
        $invoice->items()->create(['name' => 'Exempt service', 'quantity' => 1, 'unit_price' => 100, 'line_subtotal' => 100, 'line_total' => 100]);

        $render = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));
        $output = view('invoice-templates.layouts.a4-corporate', ['invoice' => $invoice, 'render' => $render])->render();

        $this->assertStringContainsString('>INVOICE<', $output);
        $this->assertStringNotContainsString('>TAX INVOICE<', $output);
        $this->assertStringNotContainsString('GST summary', $output);
    }

    private function manager(): User
    {
        $company = Company::factory()->create(['industry' => 'general_retail']);
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }

    private function leadId(User $user): int
    {
        $source = \App\Models\Crm\CrmLeadSource::create(['company_id' => $user->company_id, 'name' => 'Website', 'slug' => 'website', 'is_active' => true]);
        $status = \App\Models\Crm\CrmLeadStatus::create(['company_id' => $user->company_id, 'name' => 'New', 'slug' => 'new', 'stage_type' => LeadStageType::New, 'is_active' => true]);

        return \App\Models\Crm\CrmLead::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'source_id' => $source->id,
            'status_id' => $status->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Numbering test lead',
            'priority' => LeadPriority::Medium,
        ])->id;
    }
}
