<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmInvoiceReturn;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class CrmInvoiceReturnPdfService
{
    public function document(CrmInvoiceReturn $return): DompdfDocument
    {
        return Pdf::loadView('pdf.crm-credit-note', ['return' => $return->loadMissing(['items', 'invoice'])])->setPaper('a4');
    }

    public function filename(CrmInvoiceReturn $return): string
    {
        $number = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $return->credit_note_number), '-');

        return 'RetailPOS-Credit-Note-'.($number ?: $return->id).'.pdf';
    }
}
