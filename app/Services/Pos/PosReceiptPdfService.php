<?php

namespace App\Services\Pos;

use App\Models\Compliance\GstSetting;
use App\Models\Pos\PosSale;
use App\Services\Crm\InvoiceTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class PosReceiptPdfService
{
    public function __construct(private readonly InvoiceTemplateService $templates) {}

    public function document(PosSale $sale, ?GstSetting $gst = null): DompdfDocument
    {
        $sale->loadMissing('company');

        return Pdf::loadView('pdf.pos-receipt', ['sale' => $sale, 'gst' => $gst, 'branding' => $this->templates->brandingFor($sale->company)])->setPaper('a4');
    }

    public function filename(PosSale $sale): string
    {
        return 'RetailPOS-Receipt-'.($sale->receipt_number ?: $sale->sale_number).'.pdf';
    }
}
