<?php

namespace App\Services\Saas;

use App\Models\SaasBillingPayment;
use App\Models\SaasSubscriptionInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class SaasInvoicePdfService
{
    public function invoice(SaasSubscriptionInvoice $invoice): DompdfDocument { return Pdf::loadView('pdf.saas-subscription-invoice', compact('invoice'))->setPaper('a4'); }
    public function receipt(SaasSubscriptionInvoice $invoice, SaasBillingPayment $payment): DompdfDocument { return Pdf::loadView('pdf.saas-subscription-receipt', compact('invoice', 'payment'))->setPaper('a4'); }
    public function invoiceFilename(SaasSubscriptionInvoice $invoice): string { return 'subscription-invoice-'.$invoice->invoice_number.'.pdf'; }
    public function receiptFilename(SaasBillingPayment $payment): string { return 'subscription-receipt-'.$payment->receipt_number.'.pdf'; }
}
