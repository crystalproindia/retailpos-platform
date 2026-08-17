<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmQuotation;
use App\Models\User;
use App\Services\Crm\InvoiceTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDocumentTotalsDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_total_summaries_hide_taxable_value_in_a4_a5_and_thermal_formats(): void
    {
        [$manager, $invoice] = $this->invoice();
        $templates = app(InvoiceTemplateService::class);

        foreach ([
            ['structured_gst_grid', 'a4'],
            ['a5_compact_gst', 'a5'],
            ['thermal_80_gst_detailed', 'thermal_80'],
            ['thermal_58_gst_compact', 'thermal_58'],
        ] as [$templateKey, $paperFormat]) {
            $templates->update($manager->company, $manager, $this->settings($templates, $templateKey, $paperFormat));
            $record = $invoice->fresh()->load(['company', 'items']);
            $render = $templates->renderData($record);
            $markup = view($templates->definitions()[$templateKey]['view'], ['invoice' => $record, 'render' => $render])->render();

            $this->assertStringNotContainsString('<td>Taxable value</td>', $markup, $templateKey);
            $this->assertStringNotContainsString('<td>Taxable</td>', $markup, $templateKey);
            $this->assertStringContainsString($paperFormat === 'a4' || $paperFormat === 'a5' ? 'Invoice total' : '>Total<', $markup, $templateKey);
            $this->assertStringContainsString(str_starts_with($paperFormat, 'thermal_') ? '>GST<' : 'CGST', $markup, $templateKey);
            if (! str_starts_with($paperFormat, 'thermal_')) {
                $this->assertStringContainsString('SGST', $markup, $templateKey);
            }
        }

        $invoice->refresh();
        $this->assertSame('1000.00', $invoice->taxable_total);
        $this->assertSame('90.00', $invoice->cgst_total);
        $this->assertSame('90.00', $invoice->sgst_total);
        $this->assertSame('1180.00', $invoice->grand_total);
    }

    public function test_no_gst_invoice_summary_hides_subtotal_without_changing_totals(): void
    {
        [$manager, $invoice] = $this->invoice([
            'tax_mode' => 'no_gst',
            'taxable_total' => 1000,
            'tax_total' => 0,
            'cgst_total' => 0,
            'sgst_total' => 0,
            'grand_total' => 1000,
            'balance_due' => 1000,
        ]);
        $templates = app(InvoiceTemplateService::class);

        foreach ([['modern_blue_corporate', 'a4'], ['thermal_58_mini', 'thermal_58']] as [$templateKey, $paperFormat]) {
            $templates->update($manager->company, $manager, $this->settings($templates, $templateKey, $paperFormat));
            $record = $invoice->fresh()->load(['company', 'items']);
            $render = $templates->renderData($record);
            $markup = view($templates->definitions()[$templateKey]['view'], ['invoice' => $record, 'render' => $render])->render();

            $this->assertStringNotContainsString('Subtotal after discount', $markup, $templateKey);
            $this->assertStringNotContainsString('<td>Subtotal</td>', $markup, $templateKey);
            $this->assertStringNotContainsString('CGST', $markup, $templateKey);
            $this->assertStringContainsString('INR 1,000.00', $markup, $templateKey);
        }

        $invoice->refresh();
        $this->assertSame('1000.00', $invoice->taxable_total);
        $this->assertSame('0.00', $invoice->tax_total);
        $this->assertSame('1000.00', $invoice->grand_total);
    }

    public function test_quotation_and_proforma_outputs_do_not_render_taxable_value_rows(): void
    {
        $company = Company::factory()->create(['currency' => 'INR']);
        $quotation = new CrmQuotation([
            'company_id' => $company->id,
            'quotation_number' => 'QUOTE-TOTALS-001',
            'title' => 'Retail proposal',
            'currency' => 'INR',
            'subtotal' => 1000,
            'discount_total' => 100,
            'tax_total' => 162,
            'grand_total' => 1062,
        ]);
        $quotation->setRelation('company', $company);
        $quotation->setRelation('items', collect());
        $proforma = new CrmProformaInvoice([
            'company_id' => $company->id,
            'proforma_number' => 'PROFORMA-TOTALS-001',
            'currency' => 'INR',
            'subtotal' => 1000,
            'tax_total' => 180,
            'grand_total' => 1180,
            'paid_amount' => 180,
            'balance_amount' => 1000,
        ]);
        $proforma->setRelation('company', $company);
        $proforma->setRelation('items', collect());
        $presentation = ['payment_details' => null, 'watermark' => ['enabled' => false, 'data_uri' => null, 'opacity' => 0.12, 'position' => 'center']];
        $signature = ['data_uri' => null, 'name' => null, 'designation' => null];

        $quotationMarkup = view('pdf.crm-quotation', compact('quotation', 'presentation', 'signature') + ['isGst' => true])->render();
        $proformaMarkup = view('pdf.crm-proforma', compact('proforma', 'presentation', 'signature') + ['isGst' => true])->render();

        $this->assertStringNotContainsString('Taxable value', $quotationMarkup);
        $this->assertStringContainsString('Grand total', $quotationMarkup);
        $this->assertStringNotContainsString('Taxable value', $proformaMarkup);
        $this->assertStringContainsString('>GST<', $proformaMarkup);
        $this->assertStringContainsString('INR 1,180.00', $proformaMarkup);
    }

    /** @return array<string, mixed> */
    private function settings(InvoiceTemplateService $templates, string $templateKey, string $paperFormat): array
    {
        return [
            'template_key' => $templateKey,
            'paper_format' => $paperFormat,
            'brand_color' => '#0f766e',
            'copy_label' => 'customer_copy',
            'orientation' => 'portrait',
            'gst_presentation' => 'detailed',
            'options' => $templates->defaultOptions(),
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function invoice(array $overrides = []): array
    {
        $company = Company::factory()->create(['currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();
        $manager = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $invoice = CrmInvoice::create(array_replace([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_number' => 'TOTALS-'.$company->id,
            'currency' => 'INR',
            'status' => InvoiceStatus::Issued,
            'taxable_total' => 1000,
            'tax_total' => 180,
            'cgst_total' => 90,
            'sgst_total' => 90,
            'igst_total' => 0,
            'cess_total' => 0,
            'grand_total' => 1180,
            'amount_paid' => 180,
            'balance_due' => 1000,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ], $overrides));
        $invoice->items()->create([
            'name' => 'RetailPOS subscription',
            'hsn_sac' => '998314',
            'quantity' => 1,
            'unit' => 'service',
            'unit_price' => 1000,
            'tax_rate' => (float) $invoice->tax_total === 0.0 ? 0 : 18,
            'tax_amount' => $invoice->tax_total,
            'cgst_amount' => $invoice->cgst_total,
            'sgst_amount' => $invoice->sgst_total,
            'igst_amount' => $invoice->igst_total,
            'cess_amount' => $invoice->cess_total,
            'line_subtotal' => $invoice->taxable_total,
            'line_total' => $invoice->grand_total,
        ]);

        return [$manager, $invoice];
    }
}
