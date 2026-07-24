<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class InvoicePdfService
{
    private const TEMPLATE_VIEWS = [
        'structured_gst_grid' => 'invoice-templates.structured-gst-grid',
        'premium_elegant' => 'invoice-templates.premium-elegant',
        'compact_detailed_gst' => 'invoice-templates.compact-detailed-gst',
        'modern_split_panel' => 'invoice-templates.modern-split-panel',
        'executive_corporate_gst' => 'invoice-templates.executive-corporate-gst',
    ];

    public function __construct(private readonly InvoiceTemplateService $templates) {}
    public function document(CrmInvoice $invoice): DompdfDocument { $render = $this->templates->renderData($invoice->loadMissing(['company', 'items'])); return Pdf::loadView($this->templateView($render['setting']->template_key), ['invoice' => $invoice, 'render' => $render])->setPaper('a4', $render['setting']->orientation); }
    public function templateView(string $templateKey): string { return self::TEMPLATE_VIEWS[$templateKey] ?? self::TEMPLATE_VIEWS['structured_gst_grid']; }
    public function receipt(CrmInvoice $invoice, \App\Models\Crm\CrmInvoicePayment $payment): DompdfDocument { return Pdf::loadView('pdf.crm-payment-receipt', compact('invoice', 'payment'))->setPaper('a4'); }
    public function filename(CrmInvoice $invoice): string { return 'RetailPOS-Invoice-'.$invoice->invoice_number.'.pdf'; }
    public function receiptFilename(\App\Models\Crm\CrmInvoicePayment $payment): string { return 'RetailPOS-Receipt-'.$payment->receipt_number.'.pdf'; }
}
