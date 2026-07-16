<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmProformaInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class ProformaPdfService
{
    public function document(CrmProformaInvoice $proforma): DompdfDocument
    {
        return Pdf::loadView('pdf.crm-proforma', ['proforma' => $proforma])
            ->setPaper('a4');
    }

    public function binary(CrmProformaInvoice $proforma): string
    {
        return $this->document($proforma)->output();
    }

    public function filename(CrmProformaInvoice $proforma): string
    {
        return str($proforma->proforma_number ?: 'retailpos-proforma')
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->append('.pdf')
            ->toString();
    }
}
