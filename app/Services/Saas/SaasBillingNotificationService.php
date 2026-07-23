<?php

namespace App\Services\Saas;

use App\Models\SaasBillingPayment;
use App\Models\SaasSubscriptionInvoice;
use App\Services\Notifications\EmailDeliveryService;

class SaasBillingNotificationService
{
    public function __construct(private readonly EmailDeliveryService $emails) {}

    public function invoiceIssued(SaasSubscriptionInvoice $invoice): void
    {
        if (blank($invoice->billing_email)) {
            return;
        }

        $this->emails->queue(
            $invoice->company_id,
            $invoice->billing_email,
            'Subscription invoice '.$invoice->invoice_number,
            'saas_billing_invoice_issued',
            [
                'heading' => 'Your subscription invoice is ready',
                'greeting' => $invoice->billing_name ?: 'Hello,',
                'message' => 'Your RetailPOS subscription invoice is ready for review and payment.',
                'details' => [
                    'Invoice' => $invoice->invoice_number,
                    'Amount due' => $invoice->currency.' '.$invoice->balance_due,
                    'Due date' => $invoice->due_date?->format('d M Y') ?: 'On issue',
                ],
                'action_url' => route('account.subscription.billing.show', $invoice),
                'action_label' => 'View invoice',
            ],
            $invoice,
            idempotencyKey: 'saas-billing-invoice-issued:'.$invoice->id,
            recipientName: $invoice->billing_name,
        );
    }

    public function paymentConfirmed(SaasBillingPayment $payment): void
    {
        $invoice = $payment->invoice;
        if (! $invoice || blank($invoice->billing_email)) {
            return;
        }

        $this->emails->queue(
            $payment->company_id,
            $invoice->billing_email,
            'Subscription payment received: '.$payment->receipt_number,
            'saas_billing_receipt',
            [
                'heading' => 'Subscription payment received',
                'greeting' => $invoice->billing_name ?: 'Hello,',
                'message' => 'We have confirmed your subscription payment. Your receipt is ready.',
                'details' => [
                    'Receipt' => $payment->receipt_number,
                    'Invoice' => $invoice->invoice_number,
                    'Amount received' => $payment->currency.' '.$payment->amount,
                    'Payment method' => str($payment->payment_method)->headline()->toString(),
                ],
                'action_url' => route('account.subscription.billing.show', $invoice),
                'action_label' => 'View receipt',
            ],
            $payment,
            idempotencyKey: 'saas-billing-receipt:'.$payment->id,
            recipientName: $invoice->billing_name,
        );
    }
}
