<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Repositories\Crm\InvoiceRepository;
use App\Services\Branding\CompanyBrandingService;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoicePaymentQrService;
use App\Services\Crm\InvoiceTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceTemplateController extends Controller
{
    public function index(Request $request, InvoiceTemplateService $templates, InvoiceRepository $invoices, CompanyBrandingService $branding): View
    {
        return view('command-center.crm.invoices.templates', [
            'setting' => $templates->setting($request->user()->company),
            'templates' => $templates->definitions(),
            'defaults' => $templates->defaultOptions(),
            'previewInvoice' => $invoices->paginate($request->user())->first(),
            'branding' => $branding->forCompany($request->user()->company),
        ]);
    }

    public function preview(Request $request, InvoiceRepository $invoices, InvoicePdfService $pdf, int $invoice): \Illuminate\Http\Response
    {
        $record = $invoices->find($request->user(), $invoice);

        return $pdf->document($record)->stream($pdf->filename($record));
    }

    public function update(Request $request, InvoiceTemplateService $templates, InvoicePaymentQrService $paymentQr): RedirectResponse
    {
        $data = $request->validate([
            'template_key' => ['required', 'in:'.implode(',', InvoiceTemplateService::KEYS)],
            'brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'copy_label' => ['required', 'in:original,duplicate,triplicate'],
            'orientation' => ['required', 'in:portrait,landscape'],
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
            'options' => ['array'],
            'options.show_logo' => ['nullable', 'boolean'], 'options.show_bill_to' => ['nullable', 'boolean'], 'options.show_ship_to' => ['nullable', 'boolean'],
            'options.logo_position' => ['nullable', 'in:left,center,right'], 'options.logo_size' => ['nullable', 'in:small,medium,large'], 'options.show_company_name' => ['nullable', 'boolean'],
            'options.show_bank_details' => ['nullable', 'boolean'], 'options.show_terms' => ['nullable', 'boolean'], 'options.show_signature' => ['nullable', 'boolean'],
            'options.show_seal' => ['nullable', 'boolean'], 'options.show_amount_words' => ['nullable', 'boolean'], 'options.show_received_amount' => ['nullable', 'boolean'],
            'options.show_previous_balance' => ['nullable', 'boolean'], 'options.show_current_balance' => ['nullable', 'boolean'], 'options.show_hsn_sac' => ['nullable', 'boolean'],
            'options.show_sku' => ['nullable', 'boolean'], 'options.show_discount' => ['nullable', 'boolean'], 'options.show_gst_breakup' => ['nullable', 'boolean'],
            'options.show_gst_summary' => ['nullable', 'boolean'], 'options.show_payment_status' => ['nullable', 'boolean'],
        ]);
        foreach (['show_gst_breakup', 'show_gst_summary', 'show_hsn_sac'] as $required) {
            $data['options'][$required] = true;
        }
        $templates->update($request->user()->company, $request->user(), $data);

        return back()->with('status', 'Invoice design saved. GST fields remain enabled for compliant invoice output.');
    }
}
