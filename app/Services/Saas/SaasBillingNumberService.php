<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\Compliance\GstDocumentSeries;
use Carbon\CarbonInterface;

class SaasBillingNumberService
{
    public function invoiceNumber(int $companyId, CarbonInterface $date): string
    {
        return $this->next($companyId, 'saas_subscription_invoice', 'SBI', $date);
    }

    public function paymentNumber(int $companyId, CarbonInterface $date): string
    {
        return $this->next($companyId, 'saas_subscription_payment', 'SBP', $date);
    }

    public function receiptNumber(int $companyId, CarbonInterface $date): string
    {
        return $this->next($companyId, 'saas_subscription_receipt', 'SBR', $date);
    }

    public function refundNumber(int $companyId, CarbonInterface $date): string
    {
        return $this->next($companyId, 'saas_subscription_refund', 'SBRF', $date);
    }

    public function financialYear(CarbonInterface $date): string
    {
        $startYear = $date->month < 4 ? $date->year - 1 : $date->year;

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }

    private function next(int $companyId, string $documentType, string $defaultPrefix, CarbonInterface $date): string
    {
        $financialYear = $this->financialYear($date);

        // The existing series constraint allows a nullable branch. Locking the tenant
        // serializes creation of its platform-level billing series across databases.
        Company::query()->lockForUpdate()->findOrFail($companyId);

        $series = GstDocumentSeries::query()
            ->where('company_id', $companyId)
            ->whereNull('branch_id')
            ->where('document_type', $documentType)
            ->where('financial_year', $financialYear)
            ->lockForUpdate()
            ->first();

        if (! $series) {
            $series = GstDocumentSeries::create([
                'company_id' => $companyId,
                'branch_id' => null,
                'document_type' => $documentType,
                'financial_year' => $financialYear,
                'prefix' => $defaultPrefix,
                'last_sequence' => 0,
                'is_active' => true,
            ]);

            $series = GstDocumentSeries::query()->lockForUpdate()->findOrFail($series->id);
        }

        $next = (int) $series->last_sequence + 1;
        $series->update(['last_sequence' => $next]);

        return sprintf('%s-%s-%06d', $series->prefix, $financialYear, $next);
    }
}
