<?php

namespace App\Services\Saas;

use App\Data\SaasBilling\CheckoutSession as GatewayCheckoutSession;
use App\Data\SaasBilling\PaymentVerification;
use App\Models\Company;
use App\Models\SaasBillingCheckoutSession;
use App\Models\SaasSubscriptionInvoice;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaasBillingCheckoutService
{
    public function __construct(
        private readonly SaasPaymentGatewayManager $gateways,
        private readonly SaasSubscriptionInvoiceService $invoices,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Company $company, SaasSubscriptionInvoice $invoice, User $actor, ?string $returnPath = null): SaasBillingCheckoutSession
    {
        return DB::transaction(function () use ($company, $invoice, $actor, $returnPath): SaasBillingCheckoutSession {
            $invoice = SaasSubscriptionInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->company_id !== $company->id || ! $invoice->isPayable()) {
                throw ValidationException::withMessages(['invoice' => 'This invoice is not available for online payment.']);
            }
            $gatewayConnection = $this->gateways->active();
            if (! $gatewayConnection) throw ValidationException::withMessages(['gateway' => 'Razorpay test mode is not configured by the platform.']);
            $key = 'checkout:'.$invoice->id.':'.hash('sha256', $invoice->balance_due.'|'.$invoice->updated_at?->timestamp);
            if ($existing = SaasBillingCheckoutSession::query()->where('company_id', $company->id)->where('idempotency_key', $key)->whereIn('status', ['created', 'pending'])->first()) return $existing;

            $session = SaasBillingCheckoutSession::create([
                'company_id' => $company->id, 'saas_subscription_invoice_id' => $invoice->id, 'saas_subscription_id' => $invoice->saas_subscription_id,
                'integration_connection_id' => $gatewayConnection->id, 'provider' => 'razorpay', 'status' => 'created',
                'currency' => $invoice->currency, 'amount' => $invoice->balance_due, 'idempotency_key' => $key,
                'return_path' => $returnPath, 'expires_at' => now()->addMinutes(30),
            ]);
            $intent = $this->gateways->gateway($gatewayConnection)->createCheckout(new GatewayCheckoutSession($session->id, $company->id, $invoice->id, $invoice->saas_subscription_id, $invoice->currency, (string) $invoice->balance_due, 'SBI-'.$invoice->id.'-'.Str::upper(Str::random(8)), $key));
            $session->update(['provider_order_id' => $intent->providerOrderId, 'metadata' => $intent->metadata]);
            $this->audit->record('saas.billing.checkout_created', $session, 'Subscription checkout created.', ['company_id' => $company->id, 'invoice_id' => $invoice->id]);

            return $session->refresh();
        });
    }

    /** @param array<string,string> $callback */
    public function verifyCallback(SaasBillingCheckoutSession $session, User $actor, array $callback): SaasBillingCheckoutSession
    {
        return DB::transaction(function () use ($session, $actor, $callback): SaasBillingCheckoutSession {
            $session = SaasBillingCheckoutSession::query()->with(['invoice', 'integration'])->lockForUpdate()->findOrFail($session->id);
            if ($session->company_id !== $actor->company_id || $session->expires_at?->isPast() || ! in_array($session->status, ['created', 'pending'], true)) return $session;
            $paymentId = (string) ($callback['razorpay_payment_id'] ?? '');
            $orderId = (string) ($callback['razorpay_order_id'] ?? '');
            $signature = (string) ($callback['razorpay_signature'] ?? '');
            if ($paymentId === '' || $orderId !== $session->provider_order_id || $signature === '') throw ValidationException::withMessages(['payment' => 'The payment callback does not match this checkout session.']);

            $verification = $this->gateways->gateway($session->integration)->verifyPayment($paymentId, $orderId, $signature);
            $this->applyVerification($session, $actor, $verification);

            return $session->refresh();
        });
    }

    public function applyVerification(SaasBillingCheckoutSession $session, ?User $actor, PaymentVerification $verification): void
    {
        if ($this->minor((string) $session->amount) !== $this->minor($verification->amount) || $session->currency !== $verification->currency) {
            $session->update(['status' => 'failed', 'failed_at' => now(), 'metadata' => ($session->metadata ?? []) + ['safe_error' => 'Gateway amount or currency did not match the invoice.']]);
            throw new SaasPaymentGatewayException('Gateway amount or currency did not match the invoice.');
        }
        if ($verification->isConfirmed()) {
            $this->invoices->recordGatewayPayment($session->invoice, $actor, $verification, $session->provider, 'checkout:'.$session->id.':'.$verification->paymentId);
            $session->update(['status' => 'paid', 'provider_payment_id' => $verification->paymentId, 'verified_at' => now()]);
            return;
        }
        $session->update(['status' => in_array($verification->status, ['failed', 'cancelled'], true) ? 'failed' : 'pending', 'provider_payment_id' => $verification->paymentId, 'submitted_at' => now(), 'failed_at' => $verification->status === 'failed' ? now() : null]);
    }

    private function minor(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
