<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmInvoiceReturnPdfService;
use App\Services\Crm\CrmInvoiceReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrmInvoiceReturnController extends Controller
{
    public function create(Request $request, CrmInvoiceReturnService $returns, int $invoice): View
    {
        $record = $returns->invoiceForReturn($request->user(), $invoice);

        return view('command-center.crm.returns.create', ['invoice' => $record, 'lines' => $returns->returnableLines($record)]);
    }

    public function store(Request $request, CrmInvoiceReturnService $returns, int $invoice): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'reason_code' => ['required', Rule::in(['damaged', 'defective', 'wrong_item', 'customer_return', 'billing_correction', 'other'])],
            'reason_note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.invoice_item_id' => ['required', 'integer'],
            'items.*.return_quantity' => ['nullable', 'decimal:0,3', 'min:0'],
            'items.*.restock' => ['nullable', 'boolean'],
            'items.*.condition_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $return = $returns->finalize($request->user(), $invoice, $data);

        return redirect()->route('sales.credit-notes.show', $return)->with('status', 'Credit note '.$return->credit_note_number.' finalized. No refund was created automatically.');
    }

    public function show(Request $request, CrmInvoiceReturnService $returns, int $return): View
    {
        return view('command-center.crm.returns.show', ['return' => $returns->findForUser($request->user(), $return)]);
    }

    public function print(Request $request, CrmInvoiceReturnService $returns, CrmInvoiceReturnPdfService $pdf, int $return): Response
    {
        $record = $returns->findForUser($request->user(), $return);

        return $pdf->document($record)->stream($pdf->filename($record));
    }

    public function pdf(Request $request, CrmInvoiceReturnService $returns, CrmInvoiceReturnPdfService $pdf, int $return): Response
    {
        $record = $returns->findForUser($request->user(), $return);

        return $pdf->document($record)->download($pdf->filename($record));
    }
}
