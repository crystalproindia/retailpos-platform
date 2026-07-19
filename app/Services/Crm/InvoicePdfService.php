<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class InvoicePdfService
{
    public function document(CrmInvoice $invoice): DompdfDocument { return Pdf::loadView('pdf.crm-invoice', ['invoice' => $invoice])->setPaper('a4'); }
    public function receipt(CrmInvoice $invoice, \App\Models\Crm\CrmInvoicePayment $payment): DompdfDocument { return Pdf::loadView('pdf.crm-payment-receipt', compact('invoice', 'payment'))->setPaper('a4'); }
    public function filename(CrmInvoice $invoice): string { return 'RetailPOS-Invoice-'.$invoice->invoice_number.'.pdf'; }
    public function receiptFilename(\App\Models\Crm\CrmInvoicePayment $payment): string { return 'RetailPOS-Receipt-'.$payment->receipt_number.'.pdf'; }
}
