<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreInvoicePaymentRequest;
use App\Http\Requests\Crm\StoreInvoiceRequest;
use App\Models\Crm\CrmInvoice;
use App\Repositories\Crm\InvoiceRepository;
use App\Repositories\Crm\QuotationRepository;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceService;
use App\Services\Crm\InvoiceShareService;
use App\Services\Crm\PublicInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request, InvoiceRepository $invoices): View { return view('command-center.crm.invoices.index', ['invoices' => $invoices->paginate($request->user(), $request->only(['search', 'status'])), 'summary' => $invoices->collectionSummary($request->user())]); }
    public function createFromQuotation(Request $request, QuotationRepository $quotations, int $quotation): View { $quote = $quotations->findForUser($request->user(), $quotation); abort_unless($quote->status?->value === 'accepted', 422); return view('command-center.crm.invoices.form', ['quotation' => $quote, 'invoice' => null]); }
    public function storeFromQuotation(Request $request, QuotationRepository $quotations, InvoiceService $service, int $quotation): RedirectResponse { $invoice = $service->createFromQuotation($quotations->findForUser($request->user(), $quotation), $request->user()); return redirect()->route('sales.invoices.show', $invoice)->with('status', 'Invoice created from accepted quotation.'); }
    public function create(): View { return view('command-center.crm.invoices.form', ['quotation' => null, 'invoice' => null]); }
    public function store(StoreInvoiceRequest $request, InvoiceService $service): RedirectResponse { $invoice = $service->create($request->user(), $request->validated()); return redirect()->route('sales.invoices.show', $invoice)->with('status', 'Draft invoice created.'); }
    public function export(Request $request, InvoiceRepository $invoices): StreamedResponse { $records = $invoices->export($request->user(), $request->only(['search', 'status'])); return response()->streamDownload(function () use ($records): void { $output = fopen('php://output', 'w'); fputcsv($output, ['Invoice number', 'Quotation number', 'Customer', 'Email', 'Currency', 'Total', 'Amount paid', 'Balance due', 'Status', 'Issue date', 'Due date']); foreach ($records as $invoice) { fputcsv($output, [$invoice->invoice_number, $invoice->quotation?->quotation_number, $invoice->billing_company ?: $invoice->billing_name, $invoice->billing_email, $invoice->currency, $invoice->grand_total, $invoice->amount_paid, $invoice->balance_due, $invoice->isOverdue() ? 'overdue' : $invoice->status?->value, $invoice->issue_date?->toDateString(), $invoice->due_date?->toDateString()]); } fclose($output); }, 'retailpos-invoices-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']); }
    public function edit(Request $request, InvoiceRepository $invoices, int $invoice): View { $record = $invoices->find($request->user(), $invoice); abort_unless($record->status?->isEditable(), 422); return view('command-center.crm.invoices.form', ['quotation' => null, 'invoice' => $record]); }
    public function update(StoreInvoiceRequest $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice): RedirectResponse { $record = $service->update($invoices->find($request->user(), $invoice), $request->user(), $request->validated()); return redirect()->route('sales.invoices.show', $record)->with('status', 'Draft invoice updated.'); }
    public function show(Request $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice): View { $record = $service->refreshStatus($invoices->find($request->user(), $invoice)); return view('command-center.crm.invoices.show', ['invoice' => $record->load(['items', 'payments.recorder', 'quotation', 'lead'])]); }
    public function issue(Request $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice): RedirectResponse { $service->issue($invoices->find($request->user(), $invoice), $request->user()); return back()->with('status', 'Invoice issued.'); }
    public function payment(StoreInvoicePaymentRequest $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice): RedirectResponse { $payment = $service->recordPayment($invoices->find($request->user(), $invoice), $request->user(), $request->validated()); return back()->with('status', 'Payment '.$payment->receipt_number.' recorded.'); }
    public function clear(Request $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice, int $payment): RedirectResponse { $record = $invoices->find($request->user(), $invoice)->payments()->findOrFail($payment); $service->clearPayment($record, $request->user()); return back()->with('status', 'Payment marked as cleared.'); }
    public function reverse(Request $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice, int $payment): RedirectResponse { $record = $invoices->find($request->user(), $invoice)->payments()->findOrFail($payment); $service->reversePayment($record, $request->user(), (string) $request->validate(['reason' => ['required','string','max:1000']])['reason']); return back()->with('status', 'Payment reversed.'); }
    public function cancel(Request $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice): RedirectResponse { $service->cancel($invoices->find($request->user(), $invoice), $request->user()); return back()->with('status', 'Invoice cancelled.'); }
    public function pdf(Request $request, InvoiceRepository $invoices, InvoicePdfService $pdf, int $invoice): Response { $record=$invoices->find($request->user(),$invoice); return $pdf->document($record)->download($pdf->filename($record)); }
    public function receipt(Request $request, InvoiceRepository $invoices, InvoicePdfService $pdf, int $invoice, int $payment): Response { $record=$invoices->find($request->user(),$invoice); return $pdf->receipt($record,$record->payments()->findOrFail($payment))->download($pdf->receiptFilename($record->payments()->findOrFail($payment))); }
    public function send(Request $request, InvoiceRepository $invoices, InvoiceShareService $sharing, int $invoice): RedirectResponse { $result=$sharing->send($invoices->find($request->user(),$invoice),$request->user(),(string)$request->validate(['email'=>['required','email']])['email']); return back()->with($result['configured']?'status':'error',$result['configured']?'Invoice email queued.':'Invoice saved; email skipped because SMTP is not configured.'); }
    public function whatsapp(Request $request, InvoiceRepository $invoices, InvoiceShareService $sharing, int $invoice): RedirectResponse { $payload=$sharing->whatsapp($invoices->find($request->user(),$invoice),$request->user()); return $payload['whatsapp_url'] ? redirect()->away($payload['whatsapp_url']) : back()->with('whatsappMessage',$payload['message']); }
    public function reminder(Request $request, InvoiceRepository $invoices, InvoiceShareService $sharing, int $invoice): RedirectResponse { $result=$sharing->remind($invoices->find($request->user(),$invoice),$request->user(),(string)$request->validate(['email'=>['required','email']])['email']); return back()->with($result['configured']?'status':'error',$result['configured']?'Payment reminder queued.':'Reminder saved; email skipped because SMTP is not configured.'); }
    public function revokeLink(Request $request, InvoiceRepository $invoices, PublicInvoiceService $links, int $invoice): RedirectResponse { $links->revoke($invoices->find($request->user(), $invoice), $request->user()); return back()->with('status', 'Secure public link revoked.'); }
    public function sendReceipt(Request $request, InvoiceRepository $invoices, InvoiceShareService $sharing, int $invoice, int $payment): RedirectResponse { $record=$invoices->find($request->user(),$invoice)->payments()->findOrFail($payment); $result=$sharing->sendReceipt($record,$request->user()); return back()->with($result['configured']?'status':'error',$result['configured']?'Receipt email queued.':'Receipt saved; email skipped because SMTP is not configured.'); }
    public function receiptWhatsapp(Request $request, InvoiceRepository $invoices, InvoiceShareService $sharing, int $invoice, int $payment): RedirectResponse { $record=$invoices->find($request->user(),$invoice)->payments()->findOrFail($payment); $payload=$sharing->receiptWhatsapp($record,$request->user()); return $payload['whatsapp_url'] ? redirect()->away($payload['whatsapp_url']) : back()->with('whatsappMessage',$payload['message']); }
}
