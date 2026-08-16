<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmQuotation;
use App\Services\Branding\CompanyBrandingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class QuotationPdfService
{
    public function __construct(
        private readonly CompanyBrandingService $branding,
        private readonly SalesDocumentPresentationService $presentations,
    ) {}

    public function document(CrmQuotation $quotation): DompdfDocument
    {
        return Pdf::loadView('pdf.crm-quotation', [
            'quotation' => $quotation->loadMissing('company'),
            'isGst' => $quotation->tax_mode !== DocumentTaxModeService::NO_GST,
            'signature' => $this->branding->signatureForPath($quotation->signature_path_snapshot, $quotation->signatory_name_snapshot, $quotation->signatory_designation_snapshot),
            'presentation' => $this->presentations->forDocument($quotation, SalesDocumentPresentationService::QUOTATION),
        ])
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
