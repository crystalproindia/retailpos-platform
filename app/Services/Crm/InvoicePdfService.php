<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
use App\Support\Invoices\InvoiceTemplateRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class InvoicePdfService
{
    public function __construct(
        private readonly InvoiceTemplateService $templates,
        private readonly InvoiceTemplateRegistry $registry,
    ) {}

    /** @param array<string,mixed> $overrides */
    public function document(CrmInvoice $invoice, array $overrides = []): DompdfDocument
    {
        $render = $this->templates->renderData($invoice->loadMissing(['company', 'items']), $overrides);
        $document = Pdf::loadView($this->templateView($render['setting']->template_key), [
            'invoice' => $invoice,
            'render' => $render,
        ]);

        return $this->applyPaper($document, $render, $invoice);
    }

    /**
     * Render the dedicated customer-facing A4 document used by Sales Invoice
     * downloads. Print, preview, and compact document formats deliberately
     * continue to use the selected invoice template.
     */
    public function premiumCustomerDocument(CrmInvoice $invoice): DompdfDocument
    {
        $render = $this->templates->renderData($invoice->loadMissing(['company', 'items', 'opportunity', 'quotation']), [
            'paper_format' => 'a4',
            'orientation' => 'portrait',
        ]);

        return Pdf::loadView('invoice-templates.premium-customer-download', [
            'invoice' => $invoice,
            'render' => $render,
        ])->setPaper('a4', 'portrait');
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
        return $this->registry->find($templateKey)['view'];
    }

    public function receipt(CrmInvoice $invoice, CrmInvoicePayment $payment): DompdfDocument
    {
        $invoice->loadMissing('company');

        return Pdf::loadView('pdf.crm-payment-receipt', [
            'invoice' => $invoice,
            'payment' => $payment,
            'branding' => $this->templates->brandingFor($invoice->company),
        ])->setPaper('a4');
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

    /** @param array<string,mixed> $render */
    private function applyPaper(DompdfDocument $document, array $render, CrmInvoice $invoice): DompdfDocument
    {
        $format = $render['setting']->paper_format ?? 'a4';
        $orientation = $render['setting']->orientation ?? 'portrait';

        return match ($format) {
            'a5' => $document->setPaper([0, 0, 419.53, 595.28], $orientation),
            'thermal_80' => $document->setPaper([0, 0, 226.77, $this->thermalHeight($invoice, $render)], 'portrait'),
            'thermal_58' => $document->setPaper([0, 0, 164.41, $this->thermalHeight($invoice, $render)], 'portrait'),
            default => $document->setPaper('a4', $orientation),
        };
    }

    /** @param array<string,mixed> $render */
    private function thermalHeight(CrmInvoice $invoice, array $render): float
    {
        $rows = $invoice->items->count();
        $taxRows = count($render['tax_rows'] ?? []);
        $charactersPerLine = ($render['setting']->paper_format ?? null) === 'thermal_58' ? 22 : 34;
        $wrappedLines = $invoice->items->sum(
            fn ($item): int => max(0, (int) ceil(mb_strlen((string) $item->name) / $charactersPerLine) - 1),
        );

        return min(7200.0, max(576.0, 280.0 + ($rows * 22.0) + ($wrappedLines * 11.0) + ($taxRows * 20.0)));
    }
}
