<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Models\SaasBillingPayment;
use App\Models\SaasBillingRefund;
use App\Models\SaasSubscriptionInvoice;
use App\Services\Saas\SaasBillingRefundService;
use App\Services\Saas\SaasInvoicePdfService;
use App\Services\Saas\SaasSubscriptionInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SaasBillingController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = SaasSubscriptionInvoice::query()->with(['company', 'subscription.plan'])->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))->latest('id')->paginate(30)->withQueryString();
        return view('command-center.saas.billing.index', ['invoices' => $invoices, 'metrics' => ['outstanding' => SaasSubscriptionInvoice::query()->whereIn('status', ['issued', 'partially_paid', 'overdue'])->sum('balance_due'), 'overdue' => SaasSubscriptionInvoice::query()->where('status', 'overdue')->count(), 'paid_this_month' => SaasSubscriptionInvoice::query()->where('status', 'paid')->whereMonth('paid_at', now()->month)->sum('amount_paid'), 'refunds_pending' => SaasBillingRefund::query()->where('status', 'requested')->count()]]);
    }

    public function show(SaasSubscriptionInvoice $invoice): View
    {
        return view('command-center.saas.billing.show', ['invoice' => $invoice->load(['company', 'subscription.plan', 'items', 'payments.refunds', 'refunds'])]);
    }

    public function reports(): View
    {
        $issued = SaasSubscriptionInvoice::query()->whereNotIn('status', ['draft', 'void']);
        return view('command-center.saas.billing.reports', [
            'tenantRevenue' => (clone $issued)->selectRaw('company_id, sum(grand_total) as total, sum(tax_total) as tax_total')->with('company')->groupBy('company_id')->orderByDesc('total')->limit(20)->get(),
            'planRevenue' => (clone $issued)->selectRaw('saas_plan_id, sum(grand_total) as total, count(*) as invoice_count')->with('plan')->groupBy('saas_plan_id')->orderByDesc('total')->limit(20)->get(),
            'tax' => (clone $issued)->selectRaw('sum(taxable_total) as taxable, sum(cgst_total) as cgst, sum(sgst_total) as sgst, sum(igst_total) as igst, sum(cess_total) as cess, sum(amount_refunded) as refunds')->first(),
        ]);
    }

    public function issue(Request $request, SaasSubscriptionInvoice $invoice, SaasSubscriptionInvoiceService $service): RedirectResponse
    {
        $service->issue($invoice, $request->user());
        return back()->with('status', 'Subscription invoice issued.');
    }

    public function void(Request $request, SaasSubscriptionInvoice $invoice, SaasSubscriptionInvoiceService $service): RedirectResponse
    {
        $service->void($invoice, $request->user(), (string) $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason']);
        return back()->with('status', 'Subscription invoice voided.');
    }

    public function payment(Request $request, SaasSubscriptionInvoice $invoice, SaasSubscriptionInvoiceService $service): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'payment_method' => ['required', 'in:cash,bank_transfer,upi,cheque,other'], 'payment_date' => ['nullable', 'date'], 'transaction_reference' => ['nullable', 'string', 'max:160'], 'bank_name' => ['nullable', 'string', 'max:160'], 'cheque_number' => ['nullable', 'string', 'max:100']]);
        $service->recordManualPayment($invoice, $request->user(), $data);
        return back()->with('status', 'Manual payment recorded and allocated.');
    }

    public function requestRefund(Request $request, SaasBillingPayment $payment, SaasBillingRefundService $refunds): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:1000']]);
        $refunds->request($payment, $request->user(), (string) $data['amount'], $data['reason']);
        return back()->with('status', 'Refund request recorded for approval.');
    }

    public function approveRefund(Request $request, SaasBillingRefund $refund, SaasBillingRefundService $refunds): RedirectResponse
    {
        $refunds->approve($refund, $request->user());
        return back()->with('status', 'Refund processed.');
    }

    public function pdf(SaasSubscriptionInvoice $invoice, SaasInvoicePdfService $pdf): Response { return $pdf->invoice($invoice->load('items'))->download($pdf->invoiceFilename($invoice)); }
    public function receipt(SaasSubscriptionInvoice $invoice, SaasBillingPayment $payment, SaasInvoicePdfService $pdf): Response { abort_unless($payment->saas_subscription_invoice_id === $invoice->id, 404); return $pdf->receipt($invoice, $payment)->download($pdf->receiptFilename($payment)); }
}
