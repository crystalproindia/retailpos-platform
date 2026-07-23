<?php

namespace App\Services\Saas;

use App\Data\SaasBilling\RefundRequest;
use App\Models\SaasBillingPayment;
use App\Models\SaasBillingRefund;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaasBillingRefundService
{
    public function __construct(private readonly SaasBillingNumberService $numbers, private readonly SaasPaymentGatewayManager $gateways, private readonly AuditLogger $audit) {}

    public function request(SaasBillingPayment $payment, User $actor, string $amount, string $reason): SaasBillingRefund
    {
        return DB::transaction(function () use ($payment, $actor, $amount, $reason): SaasBillingRefund {
            $payment = SaasBillingPayment::query()->with('invoice')->lockForUpdate()->findOrFail($payment->id);
            $minor = $this->money($amount);
            $available = $this->money($payment->amount) - $this->money((string) $payment->refund_total);
            if ($payment->status !== 'confirmed' || $minor <= 0 || $minor > $available) throw ValidationException::withMessages(['amount' => 'The refund amount must be within the confirmed, unrefunded payment total.']);
            $refund = SaasBillingRefund::create([
                'company_id' => $payment->company_id, 'saas_billing_payment_id' => $payment->id, 'saas_subscription_invoice_id' => $payment->saas_subscription_invoice_id,
                'refund_number' => $this->numbers->refundNumber($payment->company_id, now()), 'provider' => $payment->provider, 'status' => 'requested',
                'amount' => $this->decimal($minor), 'currency' => $payment->currency, 'reason' => $reason, 'requested_by' => $actor->id,
            ]);
            $this->audit->record('saas.billing.refund_requested', $refund, 'Subscription payment refund requested.', ['company_id' => $payment->company_id, 'payment_id' => $payment->id]);
            return $refund;
        });
    }

    public function approve(SaasBillingRefund $refund, User $actor): SaasBillingRefund
    {
        return DB::transaction(function () use ($refund, $actor): SaasBillingRefund {
            $refund = SaasBillingRefund::query()->with('payment.invoice')->lockForUpdate()->findOrFail($refund->id);
            if ($refund->status !== 'requested') return $refund;
            $payment = $refund->payment;
            $providerRefund = $payment->provider === 'manual' ? ['provider_refund_id' => null, 'status' => 'processed'] : $this->gateways->gateway($this->gateways->active() ?? throw ValidationException::withMessages(['gateway' => 'The configured test gateway is unavailable.']))->refundPayment(new RefundRequest($payment->provider_payment_id, $refund->amount, $refund->currency, $refund->reason));
            $refund->update(['status' => $providerRefund['status'], 'provider_refund_id' => $providerRefund['provider_refund_id'], 'approved_by' => $actor->id, 'approved_at' => now(), 'processed_at' => now()]);
            if ($refund->status === 'processed') {
                $payment->increment('refund_total', $refund->amount);
                $invoice = $payment->invoice;
                $invoice->increment('amount_refunded', $refund->amount);
                if ($this->money((string) $invoice->amount_refunded) >= $this->money($invoice->amount_paid) && $this->money($invoice->amount_paid) > 0) $invoice->update(['status' => 'refunded', 'payment_status' => 'refunded']);
            }
            $this->audit->record('saas.billing.refund_processed', $refund, 'Subscription payment refund processed.', ['company_id' => $refund->company_id, 'payment_id' => $payment->id]);
            return $refund->refresh();
        });
    }

    private function money(string|int|float $value): int { return (int) round(((float) $value) * 100); }
    private function decimal(int $minor): string { return number_format($minor / 100, 2, '.', ''); }
}
