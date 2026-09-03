<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\User;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceTemplateService;
use App\Support\Invoices\InvoiceTemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceDownloadPdfDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_download_design_is_tenant_scoped_and_does_not_change_print_settings(): void
    {
        [$user, $invoice] = $this->invoice('First');
        [$otherUser, $otherInvoice] = $this->invoice('Second');
        $templates = app(InvoiceTemplateService::class);

        $templates->update($user->company, $user, $this->settings($templates, [
            'template_key' => 'a5_minimal',
            'paper_format' => 'a5',
            'download_pdf_design' => 'executive_navy_receivable',
        ]));

        $first = $templates->setting($user->company);
        $second = $templates->setting($otherUser->company);
        $this->assertSame('a5_minimal', $first->template_key);
        $this->assertSame('a5', $first->paper_format);
        $this->assertSame('executive_navy_receivable', $first->download_pdf_design);
        $this->assertSame('retailpos_premium_blue', $templates->downloadPdfDesign($otherUser->company));
        $this->assertSame('structured_gst_grid', $second->template_key);

        $print = $templates->renderData($invoice->fresh()->load(['company', 'items']));
        $this->assertSame('a5_minimal', $print['setting']->template_key);
        $this->assertStringStartsWith('%PDF-', app(InvoicePdfService::class)->downloadDocument($invoice->fresh()->load(['company', 'items']))->output());
        $this->assertStringStartsWith('%PDF-', app(InvoicePdfService::class)->downloadDocument($otherInvoice->fresh()->load(['company', 'items']))->output());
    }

    public function test_download_design_falls_back_to_premium_blue_for_legacy_or_invalid_values(): void
    {
        [$user, $invoice] = $this->invoice('Fallback');
        $setting = app(InvoiceTemplateService::class)->setting($user->company);
        $setting->update(['download_pdf_design' => 'thermal_58_mini']);

        $templates = app(InvoiceTemplateService::class);
        $this->assertSame('retailpos_premium_blue', $templates->downloadPdfDesign($user->company));
        $markup = view('invoice-templates.premium-customer-download', [
            'invoice' => $invoice->fresh()->load(['company', 'items']),
            'render' => $templates->renderData($invoice->fresh()->load(['company', 'items']), ['paper_format' => 'a4', 'orientation' => 'portrait']),
        ])->render();
        $this->assertStringContainsString('position:fixed', $markup);
        $this->assertStringContainsString('PAYMENT &amp; RECEIVABLES SUMMARY', $markup);
    }

    public function test_only_the_curated_a4_designs_are_accepted_for_download_configuration(): void
    {
        [$user] = $this->invoice('Validation');
        $templates = app(InvoiceTemplateService::class);

        $this->actingAs($user)->put(route('sales.invoices.templates.update'), $this->settings($templates, [
            'download_pdf_design' => 'thermal_80_modern',
        ]))->assertSessionHasErrors('download_pdf_design');

        $this->actingAs($user)->get(route('sales.invoices.templates.index'))
            ->assertOk()
            ->assertSee('Download PDF design')
            ->assertSee('A4 customer PDF')
            ->assertSee('Executive Navy');

        $this->assertSame(InvoiceTemplateRegistry::DOWNLOAD_PDF_KEYS, array_keys(app(InvoiceTemplateService::class)->downloadDefinitions()));
    }

    public function test_authorized_preview_uses_a_transient_design_and_preserves_the_saved_company_preference(): void
    {
        [$user, $invoice] = $this->invoice('Preview');
        $templates = app(InvoiceTemplateService::class);
        $before = $templates->downloadPdfDesign($user->company);

        $this->actingAs($user)->get(route('sales.invoices.templates.download-preview', [
            'invoice' => $invoice,
            'download_pdf_design' => 'modern_minimal_receivable',
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->assertSame($before, $templates->downloadPdfDesign($user->company));
    }

    public function test_download_preview_rejects_an_invoice_outside_the_current_tenant(): void
    {
        [$user] = $this->invoice('First');
        [, $otherInvoice] = $this->invoice('Other');

        $this->actingAs($user)->get(route('sales.invoices.templates.download-preview', [
            'invoice' => $otherInvoice,
            'download_pdf_design' => 'executive_navy_receivable',
        ]))->assertNotFound();
    }

    #[DataProvider('downloadDesigns')]
    public function test_every_supported_customer_download_design_generates_a_pdf(string $design): void
    {
        [$user, $invoice] = $this->invoice('Smoke');
        $templates = app(InvoiceTemplateService::class);
        $templates->update($user->company, $user, $this->settings($templates, [
            'download_pdf_design' => $design,
        ]));

        $this->assertStringStartsWith('%PDF-', app(InvoicePdfService::class)->downloadDocument($invoice->fresh()->load(['company', 'items']))->output());
    }

    public function test_premium_blue_four_item_download_keeps_the_header_in_document_flow_and_fits_one_a4_page(): void
    {
        [$user, $invoice] = $this->invoice('Layout', 4);
        $templates = app(InvoiceTemplateService::class);
        Storage::fake('local');
        $templates->update(
            $user->company,
            $user,
            $this->settings($templates, [
                'download_pdf_design' => 'retailpos_premium_blue',
                'watermark_enabled' => true,
            ]),
            UploadedFile::fake()->image('watermark.png', 600, 320),
        );
        $invoice->update([
            'watermark_path_snapshot' => $templates->setting($user->company)->watermark_path,
            'presentation_snapshot_at' => now(),
        ]);

        $markup = view('invoice-templates.premium-customer-download', [
            'invoice' => $invoice->fresh()->load(['company', 'items']),
            'render' => $templates->renderData($invoice->fresh()->load(['company', 'items']), ['paper_format' => 'a4', 'orientation' => 'portrait']),
        ])->render();
        $pdf = app(InvoicePdfService::class)->downloadDocument($invoice->fresh()->load(['company', 'items']))->output();

        $this->assertStringContainsString('.invoice-watermark { height:38%; left:15%; opacity:0.12; pointer-events:none; position:fixed;', $markup);
        $this->assertStringContainsString('invoice-watermark', $markup);
        $this->assertStringContainsString('data:image/png;base64,', $markup);
        $this->assertSame(1, preg_match_all('/\\/Type\\s*\\/Page(?!s)/', $pdf));
    }

    public function test_premium_blue_signature_uses_one_fixed_width_centered_block(): void
    {
        [$user, $invoice] = $this->invoice('Signature');
        Storage::fake('local');
        $signature = UploadedFile::fake()->image('signature.png', 160, 60);
        Storage::disk('local')->put('companies/'.$user->company_id.'/branding/signature.png', file_get_contents($signature->getRealPath()));
        $invoice->update([
            'show_authorized_signature' => true,
            'signature_path_snapshot' => 'companies/'.$user->company_id.'/branding/signature.png',
            'signatory_name_snapshot' => 'Dinesh Kumar',
            'signatory_designation_snapshot' => 'Authorized Person',
        ]);

        $fresh = $invoice->fresh()->load(['company', 'items']);
        $markup = view('invoice-templates.premium-customer-download', [
            'invoice' => $fresh,
            'render' => app(InvoiceTemplateService::class)->renderData($fresh, ['paper_format' => 'a4', 'orientation' => 'portrait']),
        ])->render();

        $this->assertStringContainsString('class="signature-block"', $markup);
        $this->assertStringContainsString('width="150" align="right"', $markup);
        $this->assertStringContainsString('class="signature-image"', $markup);
        $this->assertStringContainsString('class="signature-line"', $markup);
        $this->assertStringNotContainsString('margin:7px 0 3px auto', $markup);
    }

    /** @return array{User,CrmInvoice} */
    private function invoice(string $prefix, int $lineCount = 1): array
    {
        $company = Company::factory()->create(['currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $invoice = CrmInvoice::create([
            'company_id' => $company->id,
            'invoice_number' => $prefix.'-INV-001',
            'currency' => 'INR',
            'status' => InvoiceStatus::Issued,
            'billing_name' => $prefix.' Customer',
            'billing_address' => 'MG Road, Bengaluru',
            'taxable_total' => 1000,
            'tax_total' => 180,
            'cgst_total' => 90,
            'sgst_total' => 90,
            'igst_total' => 0,
            'cess_total' => 0,
            'grand_total' => 1180,
            'amount_paid' => 0,
            'balance_due' => 1180,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        for ($index = 1; $index <= $lineCount; $index++) {
            $invoice->items()->create([
                'name' => 'Retail setup '.$index,
                'hsn_sac' => '998314',
                'quantity' => 1,
                'unit' => 'service',
                'unit_price' => 1000,
                'tax_rate' => 18,
                'tax_amount' => 180,
                'cgst_amount' => 90,
                'sgst_amount' => 90,
                'igst_amount' => 0,
                'cess_amount' => 0,
                'line_subtotal' => 1000,
                'line_total' => 1180,
            ]);
        }

        return [$user, $invoice];
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function settings(InvoiceTemplateService $templates, array $overrides = []): array
    {
        return array_replace([
            'template_key' => 'structured_gst_grid',
            'paper_format' => 'a4',
            'brand_color' => '#0f766e',
            'copy_label' => 'original',
            'orientation' => 'portrait',
            'options' => $templates->defaultOptions(),
        ], $overrides);
    }

    /** @return iterable<string,array{string}> */
    public static function downloadDesigns(): iterable
    {
        foreach (InvoiceTemplateRegistry::DOWNLOAD_PDF_KEYS as $design) {
            yield $design => [$design];
        }
    }
}
