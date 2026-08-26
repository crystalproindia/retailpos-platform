<?php

namespace App\Services\Finance;

use App\Enums\Crm\InvoiceStatus;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoiceReturn;
use App\Models\Finance\CrmInvoicePaymentAllocation;
use App\Models\Finance\CustomerCreditAllocation;
use App\Support\Finance\FinanceAmount;

class FinanceBalanceService
{
    public function refreshInvoice(CrmInvoice $invoice, ?int $updatedBy = null): CrmInvoice
    {
        $allocatedPayments = CrmInvoicePaymentAllocation::query()->where('invoice_id', $invoice->id)
            ->whereHas('payment', fn ($query) => $query->whereIn('status', ['recorded', 'cleared']))->sum('amount');
        $legacyDirectPayments = $invoice->payments()->whereIn('status', ['recorded', 'cleared'])
            ->whereDoesntHave('allocations')->sum('amount');
        $returns = CrmInvoiceReturn::query()->where('invoice_id', $invoice->id)->where('status', 'finalized');
        $directCredits = (clone $returns)->sum('receivable_credit_applied');
        $documentCredits = (clone $returns)->sum('credit_total');
        $allocatedCredits = CustomerCreditAllocation::query()->where('invoice_id', $invoice->id)->sum('amount');
        $paidMinor = FinanceAmount::minor($allocatedPayments) + FinanceAmount::minor($legacyDirectPayments);
        $appliedCreditMinor = FinanceAmount::minor($directCredits) + FinanceAmount::minor($allocatedCredits);
        $creditedMinor = FinanceAmount::minor($documentCredits) + FinanceAmount::minor($allocatedCredits);
        $balance = max(0, FinanceAmount::minor($invoice->grand_total) - $paidMinor - $appliedCreditMinor);

        $status = $invoice->status;
        if (! in_array($status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Void], true)) {
            $status = $invoice->return_status === 'full'
                ? InvoiceStatus::Credited
                : ($balance === 0 ? InvoiceStatus::Paid : ($paidMinor + $appliedCreditMinor > 0 ? InvoiceStatus::PartiallyPaid : ($invoice->due_date?->isPast() ? InvoiceStatus::Overdue : $status)));
        }

        $invoice->update([
            'amount_paid' => FinanceAmount::decimal($paidMinor),
            'credited_total' => FinanceAmount::decimal($creditedMinor),
            'balance_due' => FinanceAmount::decimal($balance),
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::Paid ? ($invoice->paid_at ?? now()) : null,
            'updated_by' => $updatedBy ?? $invoice->updated_by,
        ]);

        return $invoice->refresh();
    }
}
