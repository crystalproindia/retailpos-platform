<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmProformaInvoice;
use App\Services\Branding\CompanyBrandingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class ProformaPdfService
{
    public function __construct(private readonly CompanyBrandingService $branding) {}

    public function document(CrmProformaInvoice $proforma): DompdfDocument
    {
        $proforma->loadMissing(['company', 'items']);

        return Pdf::loadView('pdf.crm-proforma', [
            'proforma' => $proforma,
            'isGst' => $proforma->tax_mode !== DocumentTaxModeService::NO_GST,
            'signature' => $this->branding->signatureForPath($proforma->signature_path_snapshot, $proforma->signatory_name_snapshot, $proforma->signatory_designation_snapshot),
        ])
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
