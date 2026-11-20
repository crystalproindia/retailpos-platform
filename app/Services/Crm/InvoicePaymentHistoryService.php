<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
use App\Models\Finance\CrmInvoicePaymentAllocation;
use App\Support\Finance\FinanceAmount;
use Illuminate\Support\Collection;

class InvoicePaymentHistoryService
{
    /** @return array{transactions:Collection<int,array<string,mixed>>,total_minor:int,summary_minor:int,reconciles_summary:bool} */
    public function forInvoice(CrmInvoice $invoice): array
    {
        $allocations = CrmInvoicePaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->whereHas('payment', fn ($query) => $query->whereIn('status', ['recorded', 'cleared']))
            ->with('payment')
            ->get()
            ->groupBy('payment_id');

        $transactions = $allocations->map(function (Collection $rows): ?array {
            /** @var CrmInvoicePayment|null $payment */
            $payment = $rows->first()?->payment;

            return $payment ? $this->transaction($payment, $rows->sum(fn ($row): int => FinanceAmount::minor($row->amount))) : null;
        })->filter();

        // Older invoices may have direct payment rows from before allocation records existed.
        $legacyPayments = CrmInvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', ['recorded', 'cleared'])
            ->whereDoesntHave('allocations')
            ->get();

        foreach ($legacyPayments as $payment) {
            $transactions->push($this->transaction($payment, FinanceAmount::minor($payment->amount)));
        }

        $transactions = $transactions
            ->sortBy(fn (array $transaction): string => ($transaction['sort_date'] ?? '').'-'.str_pad((string) $transaction['payment_id'], 12, '0', STR_PAD_LEFT))
            ->values();

        $totalMinor = (int) $transactions->sum('amount_minor');
        $summaryMinor = FinanceAmount::minor($invoice->amount_paid);

        return [
            'transactions' => $transactions,
            'total_minor' => $totalMinor,
            'summary_minor' => $summaryMinor,
            'reconciles_summary' => $totalMinor === $summaryMinor,
        ];
    }

    /** @return array<string,mixed> */
    private function transaction(CrmInvoicePayment $payment, int $amountMinor): array
    {
        return [
            'payment_id' => $payment->id,
            'sort_date' => $payment->payment_date?->toDateString() ?? '',
            'date' => $payment->payment_date,
            'amount_minor' => $amountMinor,
            'method' => $payment->payment_method ? str($payment->payment_method)->replace('_', ' ')->headline()->toString() : null,
            'reference' => $payment->transaction_reference ?: ($payment->payment_reference ?: $payment->receipt_number),
            'notes' => $payment->notes,
        ];
    }
}
