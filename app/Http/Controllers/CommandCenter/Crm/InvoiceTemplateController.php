<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoiceItem;
use App\Repositories\Crm\InvoiceRepository;
use App\Services\Branding\CompanyBrandingService;
use App\Services\Crm\InvoicePaymentQrService;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceTemplateService;
use App\Services\Crm\InvoiceWatermarkService;
use App\Services\Crm\SalesDocumentPresentationService;
use App\Support\Invoices\InvoiceTemplateRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvoiceTemplateController extends Controller
{
    public function index(Request $request, InvoiceTemplateService $templates, InvoiceRepository $invoices, CompanyBrandingService $branding, InvoiceWatermarkService $watermarks, SalesDocumentPresentationService $presentations): View
    {
        $previewInvoice = $invoices->paginate($request->user())->first();
        $setting = $templates->setting($request->user()->company);

        return view('command-center.crm.invoices.templates', [
            'setting' => $setting,
            'templates' => $templates->definitions(),
            'downloadTemplates' => $templates->downloadDefinitions(),
            'formats' => InvoiceTemplateRegistry::FORMATS,
            'defaults' => $templates->defaultOptions(),
            'previewInvoice' => $previewInvoice,
            'previewRouteInvoice' => $previewInvoice?->id ?? 0,
            'branding' => $branding->forCompany($request->user()->company),
            'watermarkDataUri' => $watermarks->dataUri($setting->watermark_path),
            'documentTitle' => $presentations->configuredInvoiceTitle($setting),
            'documentTitleOptions' => SalesDocumentPresentationService::INVOICE_DOCUMENT_TITLES,
        ]);
    }

    public function preview(Request $request, InvoiceRepository $invoices, InvoicePdfService $pdf, InvoicePaymentQrService $paymentQr, int $invoice): Response
    {
        $record = $invoice === 0
            ? $this->sampleInvoice($request->user()->company)
            : $invoices->find($request->user(), $invoice);

        $settings = $this->validatedSettings($request, $paymentQr, false);
        $settings['_preview_live_presentation'] = true;

        return $pdf->document($record, $settings)->stream($pdf->filename($record));
    }

    public function downloadPreview(Request $request, InvoiceRepository $invoices, InvoicePdfService $pdf, int $invoice): Response
    {
        $record = $invoice === 0
            ? $this->sampleInvoice($request->user()->company)
            : $invoices->find($request->user(), $invoice);
        $data = $request->validate([
            'download_pdf_design' => ['nullable', 'in:'.implode(',', InvoiceTemplateService::DOWNLOAD_PDF_KEYS)],
        ]);
        $design = (string) ($data['download_pdf_design'] ?? '');

        return $pdf->downloadDocument($record, $design ?: null)->stream($pdf->filename($record));
    }

    public function update(Request $request, InvoiceTemplateService $templates, InvoicePaymentQrService $paymentQr): RedirectResponse
    {
        $data = $this->validatedSettings($request, $paymentQr, true);
        foreach (['show_gst_breakup', 'show_gst_summary', 'show_hsn_sac'] as $required) {
            $data['options'][$required] = true;
        }
        $templates->update(
            $request->user()->company,
            $request->user(),
            $data,
            $request->file('watermark'),
            $request->boolean('remove_watermark'),
        );

        return back()->with('status', 'Invoice design, payment details, and watermark settings saved. GST fields remain enabled for compliant invoice output.');
    }

    /** @return array<string,mixed> */
    private function validatedSettings(Request $request, InvoicePaymentQrService $paymentQr, bool $requireTemplate): array
    {
        $rules = [
            'template_key' => [$requireTemplate ? 'required' : 'nullable', 'in:'.implode(',', InvoiceTemplateService::KEYS)],
            'download_pdf_design' => ['nullable', 'in:'.implode(',', InvoiceTemplateService::DOWNLOAD_PDF_KEYS)],
            // Existing integrations submitted only a template key. The service
            // safely derives its paper format from the registry in that case.
            'paper_format' => ['nullable', 'in:a4,a5,thermal_80,thermal_58'],
            'gst_presentation' => ['nullable', 'in:summary,detailed'],
            'brand_color' => [$requireTemplate ? 'required' : 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'copy_label' => [$requireTemplate ? 'required' : 'nullable', 'in:original,duplicate,triplicate,customer_copy,office_copy'],
            'orientation' => [$requireTemplate ? 'required' : 'nullable', 'in:portrait,landscape'],
            'document_title' => ['nullable', 'in:'.implode(',', array_keys(SalesDocumentPresentationService::INVOICE_DOCUMENT_TITLES))],
            'custom_document_title' => ['nullable', 'required_if:document_title,custom', 'string', 'max:60', 'not_regex:/[<>\\p{Cc}]/u'],
            'payment_qr_uri' => [
                'nullable',
                'string',
                'max:512',
                function (string $attribute, mixed $value, \Closure $fail) use ($paymentQr): void {
                    if (! $paymentQr->isValidSource(is_string($value) ? $value : null)) {
                        $fail('Enter a valid UPI ID, UPI payment URI, or HTTPS payment URL without sensitive credentials.');
                    }
                },
            ],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'replace_account_number' => ['nullable', 'boolean'],
            'account_number' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9 .\\-\\/]+$/'],
            'ifsc_code' => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'bank_branch_name' => ['nullable', 'string', 'max:255'],
            'swift_bic' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{8}([A-Za-z0-9]{3})?$/'],
            'upi_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]{2,}@[A-Za-z0-9.-]{2,}$/'],
            'payment_url' => ['nullable', 'url:https', 'max:2048'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            'watermark_enabled' => ['nullable', 'boolean'],
            'watermark' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
            'remove_watermark' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.show_logo' => ['nullable', 'boolean'], 'options.show_bill_to' => ['nullable', 'boolean'], 'options.show_ship_to' => ['nullable', 'boolean'],
            'options.logo_position' => ['nullable', 'in:left,center,right'], 'options.logo_size' => ['nullable', 'in:small,medium,large'], 'options.show_company_name' => ['nullable', 'boolean'],
            'options.payment_details_enabled' => ['nullable', 'boolean'], 'options.show_bank_details' => ['nullable', 'boolean'], 'options.show_terms' => ['nullable', 'boolean'], 'options.show_signature' => ['nullable', 'boolean'],
            'options.show_seal' => ['nullable', 'boolean'], 'options.show_amount_words' => ['nullable', 'boolean'], 'options.show_received_amount' => ['nullable', 'boolean'],
            'options.show_previous_balance' => ['nullable', 'boolean'], 'options.show_current_balance' => ['nullable', 'boolean'], 'options.show_hsn_sac' => ['nullable', 'boolean'],
            'options.show_sku' => ['nullable', 'boolean'], 'options.show_discount' => ['nullable', 'boolean'], 'options.show_gst_breakup' => ['nullable', 'boolean'],
            'options.show_gst_summary' => ['nullable', 'boolean'], 'options.show_payment_status' => ['nullable', 'boolean'],
            'options.show_payment_details_on_quotation' => ['nullable', 'boolean'], 'options.show_payment_details_on_proforma' => ['nullable', 'boolean'],
        ];

        $data = $request->validate($rules);
        $data['options'] ??= [];

        if (array_key_exists('document_title', $data)) {
            $data['options']['document_title'] = $data['document_title'];
            $data['options']['custom_document_title'] = $data['document_title'] === 'custom'
                ? trim((string) ($data['custom_document_title'] ?? ''))
                : null;
        }

        return $data;
    }

    private function sampleInvoice(Company $company): CrmInvoice
    {
        $invoice = new CrmInvoice([
            'company_id' => $company->id,
            'invoice_number' => 'SAMPLE-INV-001',
            'currency' => 'INR',
            'status' => InvoiceStatus::Issued,
            'billing_name' => 'Asha Sharma',
            'billing_company' => 'Asha Retail Studio',
            'billing_email' => 'asha@example.test',
            'billing_phone' => '+91 98765 43210',
            'billing_address' => 'MG Road, Bengaluru, Karnataka 560001',
            'customer_tax_number' => '29ABCDE1234F1Z5',
            'place_of_supply' => 'Karnataka',
            'place_of_supply_state_code' => '29',
            'tax_classification' => 'Intra-state supply',
            'supplier_gstin_snapshot' => $company->tax_id,
            'issue_date' => today(),
            'due_date' => today()->addDays(14),
            'subtotal' => 2500,
            'discount_total' => 100,
            'taxable_total' => 2400,
            'tax_total' => 432,
            'cgst_total' => 216,
            'sgst_total' => 216,
            'igst_total' => 0,
            'cess_total' => 0,
            'grand_total' => 2832,
            'amount_paid' => 1000,
            'balance_due' => 1832,
            'terms_conditions' => 'Thank you for choosing us. Payment is due within fourteen days.',
        ]);
        $invoice->setRelation('company', $company);
        $invoice->setRelation('items', collect([
            new CrmInvoiceItem(['name' => 'Retail POS starter setup', 'description' => 'Configuration, onboarding and staff handover', 'hsn_sac' => '998313', 'quantity' => 1, 'unit' => 'service', 'unit_price' => 1800, 'discount_amount' => 100, 'tax_rate' => 18, 'tax_treatment_snapshot' => 'standard', 'tax_amount' => 306, 'cgst_amount' => 153, 'sgst_amount' => 153, 'igst_amount' => 0, 'cess_amount' => 0, 'line_subtotal' => 1700, 'line_total' => 2006, 'sort_order' => 1]),
            new CrmInvoiceItem(['name' => 'Barcode label roll - 100 labels', 'description' => 'Thermal-compatible retail shelf labels', 'hsn_sac' => '482190', 'quantity' => 2, 'unit' => 'roll', 'unit_price' => 350, 'discount_amount' => 0, 'tax_rate' => 18, 'tax_treatment_snapshot' => 'standard', 'tax_amount' => 126, 'cgst_amount' => 63, 'sgst_amount' => 63, 'igst_amount' => 0, 'cess_amount' => 0, 'line_subtotal' => 700, 'line_total' => 826, 'sort_order' => 2]),
        ]));

        return $invoice;
    }
}
