<?php

namespace App\Services\Pos;

use App\Models\Compliance\GstSetting;
use App\Models\Pos\PosSale;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class PosReceiptPdfService
{
    public function document(PosSale $sale, ?GstSetting $gst = null): DompdfDocument
    {
        return Pdf::loadView('pdf.pos-receipt', ['sale' => $sale, 'gst' => $gst])->setPaper('a4');
    }

    public function filename(PosSale $sale): string
    {
        return 'RetailPOS-Receipt-'.($sale->receipt_number ?: $sale->sale_number).'.pdf';
    }
}
