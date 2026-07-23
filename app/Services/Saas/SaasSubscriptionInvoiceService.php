<?php

namespace App\Services\Saas;

use App\Data\SaasBilling\PaymentVerification;
use App\Models\Company;
use App\Models\SaasBillingPayment;
use App\Models\SaasSubscription;
use App\Models\SaasSubscriptionInvoice;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaasSubscriptionInvoiceService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SaasBillingNumberService $numbers,
        private readonly SaasInvoiceTaxService $taxes,
        private readonly SubscriptionService $subscriptions,
        private readonly SaasBillingNotificationService $notifications,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(SaasSubscription $subscription, ?User $actor, array $data = []): SaasSubscriptionInvoice
    {
        return DB::transaction(function () use ($subscription, $actor, $data): SaasSubscriptionInvoice {
            $subscription = SaasSubscription::query()->with(['company', 'plan.features', 'plan.limits'])->lockForUpdate()->findOrFail($subscription->id);
            $company = $subscription->company;
            $periodStart = CarbonImmutable::parse($data['billing_period_starts_at'] ?? $subscription->current_period_starts_at ?? today())->startOfDay();
            $periodEnd = CarbonImmutable::parse($data['billing_period_ends_at'] ?? $subscription->current_period_ends_at ?? $periodStart->addMonth())->startOfDay();
            $invoiceType = $data['invoice_type'] ?? 'renewal';
            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey && ($existing = SaasSubscriptionInvoice::query()->where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->first())) {
                return $existing;
            }

            if ($existing = SaasSubscriptionInvoice::query()
                ->where('company_id', $company->id)
                ->where('saas_subscription_id', $subscription->id)
                ->whereDate('billing_period_starts_at', $periodStart)
                ->whereDate('billing_period_ends_at', $periodEnd)
                ->where('invoice_type', $invoiceType)
                ->first()) {
                return $existing;
            }

            $line = $this->subscriptionLine($subscription, $company, $data);
            $issueDate = isset($data['issue_date']) ? CarbonImmutable::parse($data['issue_date']) : null;
            $dueDate = isset($data['due_date']) ? CarbonImmutable::parse($data['due_date']) : null;
            $invoice = SaasSubscriptionInvoice::create([
                'company_id' => $company->id,
                'saas_subscription_id' => $subscription->id,
                'saas_plan_id' => $subscription->saas_plan_id,
                'invoice_number' => $this->numbers->invoiceNumber($company->id, $issueDate ?? $periodStart),
                'invoice_type' => $invoiceType,
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'financial_year' => $this->numbers->financialYear($issueDate ?? $periodStart),
                'billing_period_starts_at' => $periodStart,
                'billing_period_ends_at' => $periodEnd,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'currency' => $subscription->currency,
                'billing_name' => $company->billing_contact_name,
                'billing_company' => $company->legal_name ?: $company->name,
                'billing_email' => $company->billing_contact_email ?: $company->email,
                'billing_phone' => $company->phone,
                'billing_address' => $company->address,
                'billing_country' => $company->country,
                'customer_gstin' => $company->tax_id,
                'supplier_gstin_snapshot' => $line['supplier_gstin'],
                'supplier_state_code_snapshot' => $line['supplier_state'],
                'place_of_supply_state_code' => $line['place_of_supply'],
                'tax_treatment_snapshot' => $line['treatment'],
                'reverse_charge' => $line['reverse_charge'],
                'plan_snapshot' => $this->planSnapshot($subscription),
                'subtotal' => $line['subtotal'],
                'discount_total' => $line['discount_amount'],
                'adjustment_total' => $line['adjustment_amount'],
                'credit_total' => $line['credit_amount'],
                'taxable_total' => $line['taxable_value'],
                'cgst_total' => $line['cgst_amount'],
                'sgst_total' => $line['sgst_amount'],
                'igst_total' => $line['igst_amount'],
                'cess_total' => $line['cess_amount'],
                'tax_total' => $line['tax_total'],
                'grand_total' => $line['line_total'],
                'balance_due' => $line['line_total'],
                'notes' => $data['notes'] ?? null,
                'internal_remarks' => $data['internal_remarks'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $actor?->id,
            ]);
            $invoice->items()->create($line['item']);

            $this->audit->record('saas.billing.invoice_created', $invoice, 'Subscription invoice created.', ['company_id' => $company->id]);

            return $invoice->load('items');
        });
    }

    public function issue(SaasSubscriptionInvoice $invoice, ?User $actor, ?CarbonImmutable $dueDate = null): SaasSubscriptionInvoice
    {
        $issued = DB::transaction(function () use ($invoice, $actor, $dueDate): SaasSubscriptionInvoice {
            $invoice = SaasSubscriptionInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== 'draft') {
                return $invoice;
            }
            $invoice->update([
                'status' => 'issued',
                'issue_date' => $invoice->issue_date ?? today(),
                'due_date' => $dueDate ?? $invoice->due_date ?? today(),
                'issued_by' => $actor?->id,
                'issued_at' => now(),
            ]);
            $this->audit->record('saas.billing.invoice_issued', $invoice, 'Subscription invoice issued.', ['company_id' => $invoice->company_id]);

            return $invoice->refresh();
        });

        if ($issued->status === 'issued') {
            $this->notifications->invoiceIssued($issued);
        }

        return $issued;
    }

    /** @param array<string,mixed> $data */
    public function recordManualPayment(SaasSubscriptionInvoice $invoice, User $actor, array $data): SaasBillingPayment
    {
        $payment = DB::transaction(function () use ($invoice, $actor, $data): SaasBillingPayment {
            $invoice = SaasSubscriptionInvoice::query()->with('subscription')->lockForUpdate()->findOrFail($invoice->id);
            if (! $invoice->isPayable()) {
                throw ValidationException::withMessages(['invoice' => 'Only issued subscription invoices with an outstanding balance can receive payments.']);
            }
            $amount = $this->money($data['amount'] ?? '0');
            if ($amount <= 0 || $amount > $this->money($invoice->balance_due)) {
                throw ValidationException::withMessages(['amount' => 'Payment must be greater than zero and cannot exceed the outstanding invoice balance.']);
            }
            $key = $data['idempotency_key'] ?? hash('sha256', implode('|', [$invoice->id, $data['payment_date'] ?? today()->toDateString(), $amount, $data['payment_method'] ?? 'manual', $data['transaction_reference'] ?? '']));
            if ($existing = SaasBillingPayment::query()->where('company_id', $invoice->company_id)->where('idempotency_key', $key)->first()) {
                return $existing;
            }

            $paymentDate = CarbonImmutable::parse($data['payment_date'] ?? today());
            $payment = SaasBillingPayment::create([
                'company_id' => $invoice->company_id,
                'saas_subscription_invoice_id' => $invoice->id,
                'saas_subscription_id' => $invoice->saas_subscription_id,
                'payment_number' => $this->numbers->paymentNumber($invoice->company_id, $paymentDate),
                'receipt_number' => $this->numbers->receiptNumber($invoice->company_id, $paymentDate),
                'provider' => 'manual',
                'status' => 'confirmed',
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'amount' => $this->decimal($amount),
                'currency' => $invoice->currency,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'cheque_number' => $data['cheque_number'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'idempotency_key' => $key,
                'recorded_by' => $actor->id,
                'paid_at' => $paymentDate,
                'reconciliation_status' => 'reconciled',
            ]);

            $this->refreshInvoice($invoice, $actor);
            if ($invoice->refresh()->status === 'paid' && ($data['renew_subscription'] ?? true)) {
                $this->subscriptions->renew($invoice->subscription, $actor, 'manual', $payment->transaction_reference ?: $payment->payment_number, 'saas-payment:'.$payment->id);
            }
            $this->audit->record('saas.billing.payment_recorded', $payment, 'Manual subscription payment recorded.', ['company_id' => $invoice->company_id, 'invoice_id' => $invoice->id]);

            return $payment->refresh();
        });

        if ($payment->status === 'confirmed') {
            $this->notifications->paymentConfirmed($payment->loadMissing('invoice'));
        }

        return $payment;
    }

    public function void(SaasSubscriptionInvoice $invoice, User $actor, string $reason): SaasSubscriptionInvoice
    {
        return DB::transaction(function () use ($invoice, $actor, $reason): SaasSubscriptionInvoice {
            $invoice = SaasSubscriptionInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->amount_paid > 0) {
                throw ValidationException::withMessages(['invoice' => 'A payment must be reversed or refunded before this invoice can be voided.']);
            }
            if ($invoice->status === 'void') {
                return $invoice;
            }
            if ($invoice->status !== 'draft' && $invoice->status !== 'issued') {
                throw ValidationException::withMessages(['invoice' => 'Only draft or issued invoices can be voided.']);
            }
            $invoice->update(['status' => 'void', 'payment_status' => 'void', 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason]);
            $this->audit->record('saas.billing.invoice_voided', $invoice, 'Subscription invoice voided.', ['company_id' => $invoice->company_id]);

            return $invoice->refresh();
        });
    }

    public function recordGatewayPayment(SaasSubscriptionInvoice $invoice, ?User $actor, PaymentVerification $verification, string $provider, string $idempotencyKey): SaasBillingPayment
    {
        $payment = DB::transaction(function () use ($invoice, $actor, $verification, $provider, $idempotencyKey): SaasBillingPayment {
            $invoice = SaasSubscriptionInvoice::query()->with('subscription')->lockForUpdate()->findOrFail($invoice->id);
            if ($existing = SaasBillingPayment::query()->where('provider', $provider)->where('provider_payment_id', $verification->paymentId)->first()) {
                return $existing;
            }
            if (! $invoice->isPayable()) {
                throw ValidationException::withMessages(['invoice' => 'This invoice is no longer available for payment confirmation.']);
            }
            $amount = $this->money($verification->amount);
            if ($amount <= 0 || $amount > $this->money($invoice->balance_due) || $invoice->currency !== $verification->currency) {
                throw ValidationException::withMessages(['payment' => 'The verified gateway payment does not match the outstanding invoice balance.']);
            }

            $payment = SaasBillingPayment::create([
                'company_id' => $invoice->company_id,
                'saas_subscription_invoice_id' => $invoice->id,
                'saas_subscription_id' => $invoice->saas_subscription_id,
                'payment_number' => $this->numbers->paymentNumber($invoice->company_id, now()),
                'receipt_number' => $this->numbers->receiptNumber($invoice->company_id, now()),
                'provider' => $provider,
                'provider_payment_id' => $verification->paymentId,
                'provider_order_id' => $verification->orderId,
                'status' => 'confirmed',
                'payment_method' => $verification->method,
                'amount' => $this->decimal($amount),
                'currency' => $verification->currency,
                'transaction_reference' => $verification->paymentId,
                'metadata' => $verification->metadata,
                'idempotency_key' => $idempotencyKey,
                'recorded_by' => $actor?->id,
                'paid_at' => now(),
                'reconciliation_status' => 'matched',
            ]);
            $this->refreshInvoice($invoice, $actor);
            if ($invoice->refresh()->status === 'paid') {
                $this->subscriptions->renew($invoice->subscription, $actor, $provider, $verification->paymentId, 'saas-payment:'.$payment->id);
            }
            $this->audit->record('saas.billing.gateway_payment_confirmed', $payment, 'Gateway subscription payment confirmed.', ['company_id' => $invoice->company_id, 'invoice_id' => $invoice->id]);

            return $payment->refresh();
        });

        if ($payment->status === 'confirmed') {
            $this->notifications->paymentConfirmed($payment->loadMissing('invoice'));
        }

        return $payment;
    }

    private function refreshInvoice(SaasSubscriptionInvoice $invoice, ?User $actor): void
    {
        $paid = $invoice->payments()->where('status', 'confirmed')->sum('amount');
        $balance = max(0, $this->money($invoice->grand_total) - $this->money((string) $paid));
        $status = $balance === 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'issued');
        $invoice->update([
            'amount_paid' => $this->decimal($this->money((string) $paid)),
            'balance_due' => $this->decimal($balance),
            'status' => $status,
            'payment_status' => $status === 'paid' ? 'paid' : ($status === 'partially_paid' ? 'partially_paid' : 'unpaid'),
            'paid_at' => $status === 'paid' ? ($invoice->paid_at ?? now()) : null,
        ]);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function subscriptionLine(SaasSubscription $subscription, Company $company, array $data): array
    {
        $base = $this->money((string) ($data['amount'] ?? $subscription->price_snapshot));
        $discount = $this->money((string) ($data['discount_amount'] ?? 0));
        $adjustment = $this->money((string) ($data['adjustment_amount'] ?? 0));
        $credit = $this->money((string) ($data['credit_amount'] ?? 0));
        $taxable = $base - $discount + $adjustment - $credit;
        if ($taxable < 0) {
            throw ValidationException::withMessages(['amount' => 'Discounts, credits, and adjustments cannot reduce an invoice below zero.']);
        }
        $tax = $this->taxes->calculate(
            $company,
            $this->decimal($taxable),
            (string) ($data['tax_rate'] ?? $subscription->tax_snapshot),
            $data['place_of_supply_state_code'] ?? null,
            (string) ($data['cess_rate'] ?? 0),
            (bool) ($data['reverse_charge'] ?? false),
        );

        return [
            'subtotal' => $this->decimal($base), 'discount_amount' => $this->decimal($discount), 'adjustment_amount' => $this->decimal($adjustment),
            'credit_amount' => $this->decimal($credit), 'taxable_value' => $tax['taxable_value'], 'cgst_amount' => $tax['cgst'],
            'sgst_amount' => $tax['sgst'], 'igst_amount' => $tax['igst'], 'cess_amount' => $tax['cess'],
            'tax_total' => $tax['tax_total'], 'line_total' => $tax['line_total'], 'treatment' => $tax['treatment'],
            'supplier_gstin' => $tax['supplier_gstin'], 'supplier_state' => $tax['supplier_state'], 'place_of_supply' => $tax['place_of_supply'],
            'reverse_charge' => $tax['reverse_charge'],
            'item' => [
                'line_type' => $data['line_type'] ?? 'plan_subscription',
                'name' => $data['line_name'] ?? (($subscription->plan?->name ?? 'Subscription').' subscription'),
                'description' => $data['line_description'] ?? null,
                'hsn_sac' => $data['hsn_sac'] ?? null,
                'quantity' => '1.000', 'unit' => 'service', 'unit_price' => $this->decimal($base),
                'discount_amount' => $this->decimal($discount), 'adjustment_amount' => $this->decimal($adjustment),
                'credit_amount' => $this->decimal($credit), 'taxable_value' => $tax['taxable_value'],
                'tax_rate' => $data['tax_rate'] ?? $subscription->tax_snapshot, 'cgst_amount' => $tax['cgst'],
                'sgst_amount' => $tax['sgst'], 'igst_amount' => $tax['igst'], 'cess_amount' => $tax['cess'],
                'line_total' => $tax['line_total'], 'sort_order' => 1,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function planSnapshot(SaasSubscription $subscription): array
    {
        return [
            'plan_id' => $subscription->saas_plan_id,
            'plan_code' => $subscription->plan?->code,
            'plan_name' => $subscription->plan?->name,
            'billing_interval' => $subscription->billing_interval,
            'price' => $subscription->price_snapshot,
            'setup_fee' => $subscription->setup_fee_snapshot,
            'tax_percentage' => $subscription->tax_snapshot,
            'features' => $subscription->feature_snapshot,
            'limits' => $subscription->limit_snapshot,
        ];
    }

    private function money(string|int|float $value): int
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', trim((string) $value), $matches)) {
            throw ValidationException::withMessages(['amount' => 'A valid decimal amount is required.']);
        }
        $fraction = substr(str_pad($matches[3] ?? '', 2, '0'), 0, 2);
        $minor = ((int) $matches[2] * 100) + (int) $fraction;

        return $matches[1] === '-' ? -$minor : $minor;
    }

    private function decimal(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign.intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
