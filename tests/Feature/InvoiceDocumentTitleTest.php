<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmQuotation;
use App\Models\User;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceService;
use App\Services\Crm\InvoiceTemplateService;
use App\Services\Crm\ProformaPdfService;
use App\Services\Crm\QuotationPdfService;
use App\Services\Crm\SalesDocumentPresentationService;
use App\Support\Invoices\InvoiceTemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDocumentTitleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_standard_document_title_choices_render_in_invoice_templates_and_pdfs(): void
    {
        $manager = $this->manager();

        foreach ([
            'invoice' => 'INVOICE',
            'tax_invoice' => 'TAX INVOICE',
            'gst_invoice' => 'GST INVOICE',
            'sales_invoice' => 'SALES INVOICE',
            'commercial_invoice' => 'COMMERCIAL INVOICE',
        ] as $selection => $expectedTitle) {
            $this->saveSettings($manager, ['document_title' => $selection])->assertRedirect();
            $invoice = $this->invoice($manager);
            $render = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));

            $this->assertSame($expectedTitle, $invoice->document_title_snapshot);
            $this->assertSame($expectedTitle, $render['document_title']);
            $this->assertStringContainsString($expectedTitle, view('invoice-templates.structured-gst-grid', compact('invoice', 'render'))->render());
            $this->assertStringContainsString($expectedTitle, view('pdf.crm-invoice', compact('invoice', 'render'))->render());
            $this->assertStringStartsWith('%PDF-', app(InvoicePdfService::class)->document($invoice)->output());
        }
    }

    public function test_custom_document_title_is_trimmed_and_renders_exactly(): void
    {
        $manager = $this->manager();
        $this->saveSettings($manager, [
            'document_title' => 'custom',
            'custom_document_title' => '  RETAIL SALES INVOICE  ',
        ])->assertRedirect();

        $invoice = $this->invoice($manager);
        $render = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));

        $this->assertSame('RETAIL SALES INVOICE', $invoice->document_title_snapshot);
        $this->assertStringContainsString('RETAIL SALES INVOICE', view('invoice-templates.layouts.a5', compact('invoice', 'render'))->render());
        $this->assertStringContainsString('RETAIL SALES INVOICE', view('public.invoice', ['invoice' => $invoice, 'token' => 'safe-token', 'render' => $render])->render());
    }

    public function test_configured_title_renders_across_every_a4_and_a5_template(): void
    {
        $manager = $this->manager();
        $this->saveSettings($manager, [
            'document_title' => 'custom',
            'custom_document_title' => 'RETAIL SALES INVOICE',
        ])->assertRedirect();
        $invoice = $this->invoice($manager)->load(['company', 'items']);
        $templates = app(InvoiceTemplateService::class);

        foreach (app(InvoiceTemplateRegistry::class)->all() as $key => $definition) {
            if (! in_array($definition['paper_format'], ['a4', 'a5'], true)) {
                continue;
            }

            $render = $templates->renderData($invoice, [
                'template_key' => $key,
                'paper_format' => $definition['paper_format'],
                'orientation' => 'portrait',
            ]);

            $this->assertStringContainsString('RETAIL SALES INVOICE', view($definition['view'], compact('invoice', 'render'))->render(), $key);
        }
    }

    public function test_custom_document_title_validation_rejects_missing_too_long_and_html_content(): void
    {
        $manager = $this->manager();

        $this->saveSettings($manager, ['document_title' => 'custom', 'custom_document_title' => ''])
            ->assertSessionHasErrors('custom_document_title');
        $this->saveSettings($manager, ['document_title' => 'custom', 'custom_document_title' => str_repeat('A', 61)])
            ->assertSessionHasErrors('custom_document_title');
        $this->saveSettings($manager, ['document_title' => 'custom', 'custom_document_title' => '<script>alert(1)</script>'])
            ->assertSessionHasErrors('custom_document_title');
    }

    public function test_document_title_settings_are_tenant_isolated(): void
    {
        $first = $this->manager();
        $second = $this->manager();
        $this->saveSettings($first, ['document_title' => 'commercial_invoice'])->assertRedirect();
        $this->saveSettings($second, ['document_title' => 'gst_invoice'])->assertRedirect();

        $firstRender = app(InvoiceTemplateService::class)->renderData($this->invoice($first)->load(['company', 'items']));
        $secondRender = app(InvoiceTemplateService::class)->renderData($this->invoice($second)->load(['company', 'items']));

        $this->assertSame('COMMERCIAL INVOICE', $firstRender['document_title']);
        $this->assertSame('GST INVOICE', $secondRender['document_title']);
    }

    public function test_document_title_snapshot_preserves_historical_invoices_and_legacy_invoices_keep_their_heading(): void
    {
        $manager = $this->manager();
        $this->saveSettings($manager, ['document_title' => 'tax_invoice'])->assertRedirect();
        $historical = $this->invoice($manager);

        $this->saveSettings($manager, ['document_title' => 'invoice'])->assertRedirect();
        $future = $this->invoice($manager);
        $legacy = CrmInvoice::create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'invoice_number' => 'LEGACY-TITLE-001',
            'currency' => 'INR',
            'status' => InvoiceStatus::Issued,
            'grand_total' => 100,
            'balance_due' => 100,
        ]);
        $legacy->items()->create(['name' => 'Legacy item', 'quantity' => 1, 'unit_price' => 100, 'line_subtotal' => 100, 'line_total' => 100]);

        $this->assertSame('TAX INVOICE', app(InvoiceTemplateService::class)->renderData($historical->load(['company', 'items']))['document_title']);
        $this->assertSame('INVOICE', app(InvoiceTemplateService::class)->renderData($future->load(['company', 'items']))['document_title']);
        $this->assertSame('TAX INVOICE', app(InvoiceTemplateService::class)->renderData($legacy->load(['company', 'items']))['document_title']);
    }

    public function test_quotation_and_proforma_titles_are_fixed_and_do_not_use_invoice_settings(): void
    {
        $manager = $this->manager();
        $this->saveSettings($manager, ['document_title' => 'commercial_invoice'])->assertRedirect();
        $company = $manager->company;
        $quotation = new CrmQuotation([
            'company_id' => $company->id,
            'quotation_number' => 'QUOTE-TITLE-001',
            'title' => 'Implementation proposal',
            'currency' => 'INR',
            'grand_total' => 100,
        ]);
        $quotation->setRelation('company', $company);
        $quotation->setRelation('items', collect());
        $proforma = new CrmProformaInvoice([
            'company_id' => $company->id,
            'proforma_number' => 'PROFORMA-TITLE-001',
            'currency' => 'INR',
            'invoice_date' => today(),
            'grand_total' => 100,
            'balance_amount' => 100,
        ]);
        $proforma->setRelation('company', $company);
        $proforma->setRelation('items', collect());
        $presentations = app(SalesDocumentPresentationService::class);
        $quotationPresentation = $presentations->forDocument($quotation, SalesDocumentPresentationService::QUOTATION);
        $proformaPresentation = $presentations->forDocument($proforma, SalesDocumentPresentationService::PROFORMA);

        $this->assertSame('QUOTATION', $quotationPresentation['document_title']);
        $this->assertSame('PROFORMA INVOICE', $proformaPresentation['document_title']);
        $this->assertStringContainsString('>QUOTATION<', view('pdf.crm-quotation', ['quotation' => $quotation, 'isGst' => false, 'signature' => ['data_uri' => null, 'name' => null, 'designation' => null], 'presentation' => $quotationPresentation])->render());
        $this->assertStringContainsString('>PROFORMA INVOICE<', view('pdf.crm-proforma', ['proforma' => $proforma, 'isGst' => false, 'signature' => ['data_uri' => null, 'name' => null, 'designation' => null], 'presentation' => $proformaPresentation])->render());
        $this->assertStringNotContainsString('COMMERCIAL INVOICE', app(QuotationPdfService::class)->document($quotation)->output());
        $this->assertStringStartsWith('%PDF-', app(ProformaPdfService::class)->document($proforma)->output());
    }

    public function test_document_title_control_is_visible_to_managers_and_preview_uses_live_selection(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->get(route('sales.invoices.templates.index'))
            ->assertOk()
            ->assertSee('Document heading')
            ->assertSee('Custom document title');

        $this->actingAs($manager)->get(route('sales.invoices.templates.preview', 0).'?document_title=sales_invoice')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function saveSettings(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->put(route('sales.invoices.templates.update'), array_replace_recursive([
            'template_key' => 'structured_gst_grid',
            'paper_format' => 'a4',
            'brand_color' => '#0f766e',
            'copy_label' => 'original',
            'orientation' => 'portrait',
            'gst_presentation' => 'detailed',
            'document_title' => 'invoice',
            'options' => app(InvoiceTemplateService::class)->defaultOptions(),
        ], $overrides));
    }

    private function invoice(User $user): CrmInvoice
    {
        return app(InvoiceService::class)->create($user, [
            'billing_name' => 'Asha Sharma',
            'billing_company' => 'Asha Retail',
            'billing_email' => 'asha@example.test',
            'currency' => 'INR',
            'items' => [[
                'name' => 'RetailPOS service',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_value' => 0,
                'tax_rate' => 0,
            ]],
        ]);
    }

    private function manager(): User
    {
        $company = Company::factory()->create(['currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }
}
