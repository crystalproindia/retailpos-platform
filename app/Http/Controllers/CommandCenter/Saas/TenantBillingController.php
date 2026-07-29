<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Models\SaasBillingCheckoutSession;
use App\Models\SaasBillingPayment;
use App\Models\SaasSubscriptionInvoice;
use App\Services\Saas\SaasBillingCheckoutService;
use App\Services\Saas\SaasInvoicePdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TenantBillingController extends Controller
{
    public function index(Request $request): View { $company = $request->user()->company; return view('command-center.saas.subscription.billing', ['invoices' => $company->saasSubscriptionInvoices()->latest('id')->paginate(20), 'payments' => $company->saasBillingPayments()->latest('paid_at')->limit(10)->get()]); }
    public function show(Request $request, SaasSubscriptionInvoice $invoice): View { abort_unless($invoice->company_id === $request->user()->company_id, 404); return view('command-center.saas.subscription.invoice', ['invoice' => $invoice->load(['items', 'payments'])]); }
    public function checkout(Request $request, SaasSubscriptionInvoice $invoice, SaasBillingCheckoutService $checkout): RedirectResponse { abort_unless($invoice->company_id === $request->user()->company_id, 404); $session = $checkout->create($request->user()->company, $invoice, $request->user(), route('account.subscription.billing.show', $invoice)); return redirect()->route('account.subscription.billing.checkout.show', [$invoice, $session]); }
    public function checkoutShow(Request $request, SaasSubscriptionInvoice $invoice, SaasBillingCheckoutSession $session): View { abort_unless($invoice->company_id === $request->user()->company_id && $session->company_id === $request->user()->company_id && $session->saas_subscription_invoice_id === $invoice->id, 404); return view('command-center.saas.subscription.checkout', ['invoice' => $invoice, 'session' => $session]); }
    public function callback(Request $request, SaasSubscriptionInvoice $invoice, SaasBillingCheckoutSession $session, SaasBillingCheckoutService $checkout): RedirectResponse { abort_unless($invoice->company_id === $request->user()->company_id && $session->company_id === $request->user()->company_id && $session->saas_subscription_invoice_id === $invoice->id, 404); $checkout->verifyCallback($session, $request->user(), $request->validate(['razorpay_payment_id' => ['required', 'string', 'max:160'], 'razorpay_order_id' => ['required', 'string', 'max:160'], 'razorpay_signature' => ['required', 'string', 'max:512']])); return redirect()->route('account.subscription.billing.show', $invoice)->with('status', 'Payment verification completed. A confirmed payment is reflected in your invoice.'); }
    public function pdf(Request $request, SaasSubscriptionInvoice $invoice, SaasInvoicePdfService $pdf): Response { abort_unless($invoice->company_id === $request->user()->company_id, 404); return $pdf->invoice($invoice->load('items'))->download($pdf->invoiceFilename($invoice)); }
    public function receipt(Request $request, SaasSubscriptionInvoice $invoice, SaasBillingPayment $payment, SaasInvoicePdfService $pdf): Response { abort_unless($invoice->company_id === $request->user()->company_id && $payment->saas_subscription_invoice_id === $invoice->id, 404); return $pdf->receipt($invoice, $payment)->download($pdf->receiptFilename($payment)); }
}
