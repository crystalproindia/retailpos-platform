<?php

namespace App\Services\Pos;

use App\Models\Compliance\GstSetting;
use App\Models\Pos\PosReturn;
use App\Services\Crm\InvoiceTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class PosReturnPdfService
{
    public function __construct(private readonly InvoiceTemplateService $templates) {}
    public function document(PosReturn $return): DompdfDocument
    {
        $return->loadMissing(['company', 'branch', 'customer', 'originalSale', 'items']);
        return Pdf::loadView('pdf.pos-return', ['return' => $return, 'gst' => GstSetting::query()->where('company_id', $return->company_id)->first(), 'branding' => $this->templates->brandingFor($return->company)])->setPaper('a4');
    }
    public function filename(PosReturn $return): string { return 'RetailPOS-Credit-Note-'.($return->credit_note_number ?: $return->return_number).'.pdf'; }
}
