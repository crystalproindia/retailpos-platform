<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\InvoiceTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceTemplateController extends Controller
{
    public function index(Request $request, InvoiceTemplateService $templates): View
    {
        return view('command-center.crm.invoices.templates', ['setting' => $templates->setting($request->user()->company), 'templates' => $templates->definitions(), 'defaults' => $templates->defaultOptions()]);
    }

    public function update(Request $request, InvoiceTemplateService $templates): RedirectResponse
    {
        $data = $request->validate([
            'template_key' => ['required', 'in:'.implode(',', InvoiceTemplateService::KEYS)],
            'brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'copy_label' => ['required', 'in:original,duplicate,triplicate'],
            'orientation' => ['required', 'in:portrait,landscape'],
            'payment_qr_uri' => ['nullable', 'string', 'max:512', 'regex:/^(upi:\/\/pay\?|https:\/\/)/i'],
            'options' => ['array'],
            'options.show_logo' => ['nullable', 'boolean'], 'options.show_bill_to' => ['nullable', 'boolean'], 'options.show_ship_to' => ['nullable', 'boolean'],
            'options.show_bank_details' => ['nullable', 'boolean'], 'options.show_terms' => ['nullable', 'boolean'], 'options.show_signature' => ['nullable', 'boolean'],
            'options.show_seal' => ['nullable', 'boolean'], 'options.show_amount_words' => ['nullable', 'boolean'], 'options.show_received_amount' => ['nullable', 'boolean'],
            'options.show_previous_balance' => ['nullable', 'boolean'], 'options.show_current_balance' => ['nullable', 'boolean'], 'options.show_hsn_sac' => ['nullable', 'boolean'],
            'options.show_sku' => ['nullable', 'boolean'], 'options.show_discount' => ['nullable', 'boolean'], 'options.show_gst_breakup' => ['nullable', 'boolean'],
            'options.show_gst_summary' => ['nullable', 'boolean'], 'options.show_payment_status' => ['nullable', 'boolean'],
        ]);
        foreach (['show_gst_breakup', 'show_gst_summary', 'show_hsn_sac'] as $required) $data['options'][$required] = true;
        $templates->update($request->user()->company, $request->user(), $data);
        return back()->with('status', 'Invoice design saved. GST fields remain enabled for compliant invoice output.');
    }
}
