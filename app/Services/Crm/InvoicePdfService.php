<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
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

    public function document(CrmInvoice $invoice): DompdfDocument
    {
        $render = $this->templates->renderData($invoice->loadMissing(['company', 'items']));

        return Pdf::loadView($this->templateView($render['setting']->template_key), [
            'invoice' => $invoice,
            'render' => $render,
        ])->setPaper('a4', $render['setting']->orientation);
    }

    /** @return array{contents: string, filename: string, mime: string} */
    public function attachment(CrmInvoice $invoice): array
    {
        return [
            'contents' => $this->document($invoice)->output(),
            'filename' => $this->filename($invoice),
            'mime' => 'application/pdf',
        ];
    }

    public function templateView(string $templateKey): string
    {
        return self::TEMPLATE_VIEWS[$templateKey] ?? self::TEMPLATE_VIEWS['structured_gst_grid'];
    }

    public function receipt(CrmInvoice $invoice, CrmInvoicePayment $payment): DompdfDocument
    {
        return Pdf::loadView('pdf.crm-payment-receipt', compact('invoice', 'payment'))->setPaper('a4');
    }

    public function filename(CrmInvoice $invoice): string
    {
        $number = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $invoice->invoice_number), '-');
        $number = $number !== '' ? $number : 'invoice-'.$invoice->id;

        return 'RetailPOS-Invoice-'.$number.'.pdf';
    }

    public function receiptFilename(CrmInvoicePayment $payment): string
    {
        return 'RetailPOS-Receipt-'.$payment->receipt_number.'.pdf';
    }
}
