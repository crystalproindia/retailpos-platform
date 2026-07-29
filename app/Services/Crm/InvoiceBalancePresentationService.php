<?php

namespace App\Services\Crm;

use App\Enums\Crm\InvoiceStatus;
use App\Models\Crm\CrmInvoice;

class InvoiceBalancePresentationService
{
    /** @return array{available:bool,previous_outstanding:?string,current_invoice_total:string,received_amount:string,applied_credits:string,current_balance:string} */
    public function forInvoice(CrmInvoice $invoice): array
    {
        if (! $invoice->customer_id) return ['available' => false, 'previous_outstanding' => null, 'current_invoice_total' => (string) $invoice->grand_total, 'received_amount' => (string) $invoice->amount_paid, 'applied_credits' => '0.00', 'current_balance' => (string) $invoice->balance_due];
        $previous = CrmInvoice::query()->where('company_id', $invoice->company_id)->where('customer_id', $invoice->customer_id)->whereKeyNot($invoice->id)->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue])->sum('balance_due');
        return ['available' => true, 'previous_outstanding' => number_format((float) $previous, 2, '.', ''), 'current_invoice_total' => (string) $invoice->grand_total, 'received_amount' => (string) $invoice->amount_paid, 'applied_credits' => '0.00', 'current_balance' => number_format((float) $previous + (float) $invoice->balance_due, 2, '.', '')];
    }
}
