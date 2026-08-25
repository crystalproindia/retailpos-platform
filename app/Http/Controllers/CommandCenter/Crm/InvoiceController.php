<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\InvoiceReminderStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\QuickCreateCrmCustomerRequest;
use App\Http\Requests\Crm\SendInvoiceReminderRequest;
use App\Http\Requests\Crm\StoreInvoicePaymentRequest;
use App\Http\Requests\Crm\StoreInvoiceRequest;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Repositories\Crm\InvoiceRepository;
use App\Repositories\Crm\QuotationRepository;
use App\Models\Inventory\Product;
use App\Services\Crm\CrmCustomerService;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceReminderService;
use App\Services\Crm\InvoiceReminderSettingsService;
use App\Services\Crm\InvoiceService;
use App\Services\Crm\InvoiceShareService;
use App\Services\Crm\PublicInvoiceService;
use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request, InvoiceRepository $invoices): View
    {
        return view('command-center.crm.invoices.index', ['invoices' => $invoices->paginate($request->user(), $request->only(['search', 'status', 'customer_id'])), 'summary' => $invoices->collectionSummary($request->user())]);
    }

    public function createFromQuotation(Request $request, QuotationRepository $quotations, int $quotation): View
    {
        $quote = $quotations->findForUser($request->user(), $quotation);
        abort_unless($quote->status?->value === 'accepted', 422);

        return view('command-center.crm.invoices.form', ['quotation' => $quote, 'invoice' => null, 'selectedCustomer' => $quote->crmCustomer]);
    }

    public function storeFromQuotation(Request $request, QuotationRepository $quotations, InvoiceService $service, int $quotation): RedirectResponse
    {
        $invoice = $service->createFromQuotation($quotations->findForUser($request->user(), $quotation), $request->user());

        return redirect()->route('sales.invoices.show', $invoice)->with('status', 'Invoice created from accepted quotation.');
    }

    public function create(Request $request, CrmCustomerRepository $customers): View
    {
        $customerId = $request->filled('customer') ? $request->integer('customer') : (int) $request->old('customer_id');
        $customer = $customerId > 0
            ? $customers->findForUser($request->user(), $customerId)
            : null;

        return view('command-center.crm.invoices.form', ['quotation' => null, 'invoice' => null, 'selectedCustomer' => $customer]);
    }

    public function customers(Request $request, CrmCustomerRepository $customers, InvoiceRepository $invoices): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);
        $records = $customers->searchForInvoice($request->user(), $data['q']);
        $outstanding = $invoices->outstandingByCustomers($request->user(), $records->pluck('id'));

        return response()->json(['customers' => $records->map(fn ($customer): array => $this->customerPayload(
            $customer,
            $outstanding->get($customer->id, '0.00'),
        ))]);
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);
        $term = trim($data['q']);
        $products = Product::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->where('status', Product::STATUS_ACTIVE)
            ->where(fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")->orWhere('barcode', 'like', "%{$term}%"))
            ->orderBy('name')->limit(20)->get(['id','name','sku','selling_price']);
        return response()->json(['products' => $products]);
    }

    public function quickCustomer(QuickCreateCrmCustomerRequest $request, CrmCustomerService $customers): JsonResponse
    {
        $customer = $customers->quickCreate($request->user(), $request->validated());

        return response()->json([
            'message' => 'Customer created and selected.',
            'customer' => $this->customerPayload($customer, '0.00'),
        ], 201);
    }

    public function store(StoreInvoiceRequest $request, InvoiceService $service): RedirectResponse
    {
        $invoice = $service->create($request->user(), $request->validated());

        return redirect()->route('sales.invoices.show', $invoice)->with('status', 'Draft invoice created.');
    }

    public function export(Request $request, InvoiceRepository $invoices): StreamedResponse
    {
        $records = $invoices->export($request->user(), $request->only(['search', 'status']));

        return response()->streamDownload(function () use ($records): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Invoice number', 'Quotation number', 'Customer', 'Email', 'Currency', 'Total', 'Amount paid', 'Balance due', 'Status', 'Issue date', 'Due date']);
            foreach ($records as $invoice) {
                fputcsv($output, [$invoice->invoice_number, $invoice->quotation?->quotation_number, $invoice->billing_company ?: $invoice->billing_name, $invoice->billing_email, $invoice->currency, $invoice->grand_total, $invoice->amount_paid, $invoice->balance_due, $invoice->isOverdue() ? 'overdue' : $invoice->status?->value, $invoice->issue_date?->toDateString(), $invoice->due_date?->toDateString()]);
            } fclose($output);
        }, 'retailpos-invoices-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function edit(Request $request, InvoiceRepository $invoices, CrmCustomerRepository $customers, int $invoice): View
    {
        $record = $invoices->find($request->user(), $invoice);
        abort_unless($record->status?->isEditable(), 422);
        $customerId = (int) $request->old('customer_id');
        $customer = $customerId > 0 ? $customers->findForUser($request->user(), $customerId) : $record->customer;

        return view('command-center.crm.invoices.form', ['quotation' => null, 'invoice' => $record, 'selectedCustomer' => $customer]);
    }

    public function update(StoreInvoiceRequest $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice): RedirectResponse
    {
        $record = $service->update($invoices->find($request->user(), $invoice), $request->user(), $request->validated());

        return redirect()->route('sales.invoices.show', $record)->with('status', 'Draft invoice updated.');
    }

    public function show(Request $request, InvoiceRepository $invoices, InvoiceService $service, InvoiceReminderSettingsService $reminderSettings, int $invoice): View
    {
        $record = $service->refreshStatus($invoices->find($request->user(), $invoice));
        $setting = $reminderSettings->ensure($request->user()->company);

        return view('command-center.crm.invoices.show', ['invoice' => $record->load(['items.returnItems.crmInvoiceReturn', 'returns.items', 'returns.creator', 'payments.recorder', 'quotation', 'lead', 'latestInvoiceEmailDelivery', 'invoiceEmailDeliveries', 'reminderEmailDeliveries.createdBy']), 'reminderRules' => $setting->rules->where('enabled', true)]);
    }

    public function issue(Request $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice): RedirectResponse
    {
        $service->issue($invoices->find($request->user(), $invoice), $request->user());

        return back()->with('status', 'Invoice issued.');
    }

    public function payment(StoreInvoicePaymentRequest $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice): RedirectResponse
    {
        $payment = $service->recordPayment($invoices->find($request->user(), $invoice), $request->user(), $request->validated());

        return back()->with('status', 'Payment '.$payment->receipt_number.' recorded.');
    }

    public function clear(Request $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice, int $payment): RedirectResponse
    {
        $record = $invoices->find($request->user(), $invoice)->payments()->findOrFail($payment);
        $service->clearPayment($record, $request->user());

        return back()->with('status', 'Payment marked as cleared.');
    }

    public function reverse(Request $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice, int $payment): RedirectResponse
    {
        $record = $invoices->find($request->user(), $invoice)->payments()->findOrFail($payment);
        $service->reversePayment($record, $request->user(), (string) $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason']);

        return back()->with('status', 'Payment reversed.');
    }

    public function cancel(Request $request, InvoiceRepository $invoices, InvoiceService $service, int $invoice): RedirectResponse
    {
        $service->cancel($invoices->find($request->user(), $invoice), $request->user());

        return back()->with('status', 'Invoice cancelled.');
    }

    public function print(Request $request, InvoiceRepository $invoices, InvoicePdfService $pdf, int $invoice): Response
    {
        $record = $invoices->find($request->user(), $invoice);

        return $pdf->document($record)->stream($pdf->filename($record));
    }

    public function pdf(Request $request, InvoiceRepository $invoices, InvoicePdfService $pdf, int $invoice): Response
    {
        $record = $invoices->find($request->user(), $invoice);

        return $pdf->document($record)->download($pdf->filename($record));
    }

    public function receipt(Request $request, InvoiceRepository $invoices, InvoicePdfService $pdf, int $invoice, int $payment): Response
    {
        $record = $invoices->find($request->user(), $invoice);

        return $pdf->receipt($record, $record->payments()->findOrFail($payment))->download($pdf->receiptFilename($record->payments()->findOrFail($payment)));
    }

    public function send(Request $request, InvoiceRepository $invoices, InvoiceShareService $sharing, int $invoice): RedirectResponse
    {
        $result = $sharing->send($invoices->find($request->user(), $invoice), $request->user(), (string) $request->validate(['email' => ['required', 'email']])['email'], attachInvoicePdf: true);

        return back()->with($result['configured'] ? 'status' : 'error', $result['configured'] ? 'Invoice email with PDF attachment queued.' : 'Invoice saved; email skipped because SMTP is not configured.');
    }

    public function resend(Request $request, InvoiceRepository $invoices, EmailDeliveryService $email, int $invoice, int $delivery): RedirectResponse
    {
        $record = $invoices->find($request->user(), $invoice);
        $email->manualResend($record->invoiceEmailDeliveries()->findOrFail($delivery), $request->user());

        return back()->with('status', 'Invoice email queued for resend with its PDF attachment.');
    }

    public function whatsapp(Request $request, InvoiceRepository $invoices, InvoiceShareService $sharing, int $invoice): RedirectResponse
    {
        $payload = $sharing->whatsapp($invoices->find($request->user(), $invoice), $request->user());

        return $payload['whatsapp_url'] ? redirect()->away($payload['whatsapp_url']) : back()->with('whatsappMessage', $payload['message']);
    }

    public function reminder(SendInvoiceReminderRequest $request, InvoiceRepository $invoices, InvoiceReminderService $reminders, int $invoice): RedirectResponse
    {
        $data = $request->validated();
        $result = $reminders->queueManual($invoices->find($request->user(), $invoice), $request->user(), InvoiceReminderStage::from($data['stage']), $request->boolean('attach_pdf'), $data['note'] ?? null);

        return back()->with($result['configured'] ? 'status' : 'error', $result['configured'] ? 'Payment reminder queued for delivery.' : 'Reminder was recorded, but SMTP is not configured.');
    }

    public function revokeLink(Request $request, InvoiceRepository $invoices, PublicInvoiceService $links, int $invoice): RedirectResponse
    {
        $links->revoke($invoices->find($request->user(), $invoice), $request->user());

        return back()->with('status', 'Secure public link revoked.');
    }

    public function sendReceipt(Request $request, InvoiceRepository $invoices, InvoiceShareService $sharing, int $invoice, int $payment): RedirectResponse
    {
        $record = $invoices->find($request->user(), $invoice)->payments()->findOrFail($payment);
        $result = $sharing->sendReceipt($record, $request->user());

        return back()->with($result['configured'] ? 'status' : 'error', $result['configured'] ? 'Receipt email queued.' : 'Receipt saved; email skipped because SMTP is not configured.');
    }

    public function receiptWhatsapp(Request $request, InvoiceRepository $invoices, InvoiceShareService $sharing, int $invoice, int $payment): RedirectResponse
    {
        $record = $invoices->find($request->user(), $invoice)->payments()->findOrFail($payment);
        $payload = $sharing->receiptWhatsapp($record, $request->user());

        return $payload['whatsapp_url'] ? redirect()->away($payload['whatsapp_url']) : back()->with('whatsappMessage', $payload['message']);
    }

    /** @return array<string, mixed> */
    private function customerPayload($customer, string $outstanding): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->display_name,
            'company_name' => $customer->company_name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'tax_number' => $customer->tax_number,
            'billing_address' => $customer->billing_address,
            'country' => $customer->country,
            'business_type' => $customer->business_type,
            'outstanding' => $outstanding,
        ];
    }
}
