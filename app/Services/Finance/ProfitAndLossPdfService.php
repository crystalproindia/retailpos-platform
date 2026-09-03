<?php

namespace App\Services\Finance;

use App\Models\Company;
use App\Services\Branding\CompanyBrandingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

class ProfitAndLossPdfService
{
    public function __construct(private readonly CompanyBrandingService $branding) {}

    /** @param array<string, mixed> $report */
    public function render(Company $company, array $report, string $scope): DompdfDocument
    {
        $logo = $this->branding->forCompany($company)['company_logo'];

        return Pdf::loadView('pdf.finance-profit-and-loss', compact('company', 'report', 'scope', 'logo'))
            ->setPaper('a4');
    }
}
