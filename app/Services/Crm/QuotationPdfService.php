<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmQuotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class QuotationPdfService
{
    public function document(CrmQuotation $quotation): DompdfDocument
    {
        return Pdf::loadView('pdf.crm-quotation', ['quotation' => $quotation])
            ->setPaper('a4');
    }

    public function binary(CrmQuotation $quotation): string
    {
        return $this->document($quotation)->output();
    }

    public function filename(CrmQuotation $quotation): string
    {
        return str($quotation->quotation_number ?: 'retailpos-proposal')
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->append('.pdf')
            ->toString();
    }
}
