<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
use App\Support\Finance\FinanceAmount;

class InvoiceReceivableSummaryService
{
    /** @return array<string, mixed> */
    public function forInvoice(CrmInvoice $invoice): array
    {
        $latest = $invoice->relationLoaded('payments')
            ? $invoice->payments
                ->filter(fn (CrmInvoicePayment $payment): bool => in_array($payment->status?->value, ['recorded', 'cleared'], true))
                ->sortByDesc(fn (CrmInvoicePayment $payment): string => ($payment->payment_date?->toDateString() ?? '').'-'.str_pad((string) $payment->id, 12, '0', STR_PAD_LEFT))
                ->first()
            : $invoice->payments()->whereIn('status', ['recorded', 'cleared'])->orderByDesc('payment_date')->orderByDesc('id')->first();

        $invoiceTotal = FinanceAmount::minor($invoice->grand_total);
        $amountReceived = FinanceAmount::minor($invoice->amount_paid);
        $creditsApplied = FinanceAmount::minor($invoice->credited_total);
        $balanceReceivable = max(0, FinanceAmount::minor($invoice->balance_due));

        return [
            'invoice_date' => $invoice->issue_date,
            'due_date' => $invoice->due_date,
            'invoice_total' => $invoiceTotal,
            'amount_received' => $amountReceived,
            'credits_applied' => $creditsApplied,
            'balance_receivable' => $balanceReceivable,
            'payment_status' => $this->paymentStatus($invoice, $amountReceived, $creditsApplied, $balanceReceivable),
            'last_payment_date' => $latest?->payment_date,
        ];
    }

    private function paymentStatus(CrmInvoice $invoice, int $amountReceived, int $creditsApplied, int $balanceReceivable): string
    {
        if ($invoice->status?->value === 'cancelled') {
            return 'Cancelled';
        }

        if ($balanceReceivable === 0) {
            return 'Paid';
        }

        return $amountReceived + $creditsApplied > 0 ? 'Partially Paid' : 'Unpaid';
    }
}
