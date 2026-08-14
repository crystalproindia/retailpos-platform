<?php

namespace App\Services\Crm;

use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmQuotation;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\SalesDocumentSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class SalesDocumentNumberService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function setting(Company $company): SalesDocumentSetting
    {
        return SalesDocumentSetting::firstOrCreate(['company_id' => $company->id], [
            'invoice_prefix' => 'RPOS',
            'quotation_prefix' => 'RPQ',
            'proforma_prefix' => 'RPI',
        ]);
    }

    /** @param array{invoice_prefix:string,quotation_prefix:string,proforma_prefix?:string|null} $data */
    public function update(Company $company, User $user, array $data): SalesDocumentSetting
    {
        $setting = $this->setting($company);
        $previous = $setting->only(['invoice_prefix', 'quotation_prefix', 'proforma_prefix']);
        $setting->update([
            'invoice_prefix' => $this->normalizePrefix($data['invoice_prefix']),
            'quotation_prefix' => $this->normalizePrefix($data['quotation_prefix']),
            'proforma_prefix' => $this->normalizePrefix($data['proforma_prefix'] ?? $setting->proforma_prefix),
            'updated_by' => $user->id,
        ]);
        $this->audit->record('sales.document_numbering.updated', $setting, 'Sales document prefixes updated.', [
            'company_id' => $company->id,
            'changed' => array_keys(array_filter($previous, fn (string $value, string $key): bool => $value !== $setting->{$key}, ARRAY_FILTER_USE_BOTH)),
        ]);

        return $setting->refresh();
    }

    public function nextInvoiceNumber(int $companyId): string
    {
        return $this->next($companyId, 'invoice');
    }

    public function nextQuotationNumber(int $companyId): string
    {
        return $this->next($companyId, 'quotation');
    }

    public function nextProformaNumber(int $companyId): string
    {
        return $this->next($companyId, 'proforma');
    }

    /** @return array{invoice:string,quotation:string,proforma:string} */
    public function previews(Company $company): array
    {
        $setting = $this->setting($company);
        $year = now()->format('Y');

        return [
            'invoice' => $this->format($setting->invoice_prefix, 'INV', $year, $this->nextSequence(CrmInvoice::class, $company->id, $year)),
            'quotation' => $this->format($setting->quotation_prefix, 'QUO', $year, $this->nextSequence(CrmQuotation::class, $company->id, $year)),
            'proforma' => $this->format($setting->proforma_prefix, 'PI', $year, $this->nextSequence(CrmProformaInvoice::class, $company->id, $year)),
        ];
    }

    public function normalizePrefix(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        if (! preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $prefix)) {
            throw ValidationException::withMessages(['prefix' => 'Use only uppercase letters, numbers, and single hyphens between parts.']);
        }

        return $prefix;
    }

    private function next(int $companyId, string $type): string
    {
        // The company row is the per-tenant serialization point, including the first document of a year.
        Company::query()->lockForUpdate()->findOrFail($companyId);
        $setting = $this->setting(Company::query()->findOrFail($companyId));
        $year = now()->format('Y');
        $model = match ($type) {
            'invoice' => CrmInvoice::class,
            'quotation' => CrmQuotation::class,
            default => CrmProformaInvoice::class,
        };
        $sequence = $this->nextSequence($model, $companyId, $year);

        $prefix = match ($type) {
            'invoice' => $setting->invoice_prefix,
            'quotation' => $setting->quotation_prefix,
            default => $setting->proforma_prefix,
        };
        $label = match ($type) {
            'invoice' => 'INV',
            'quotation' => 'QUO',
            default => 'PI',
        };

        return $this->format($prefix, $label, $year, $sequence);
    }

    /** @param class-string<CrmInvoice|CrmQuotation|CrmProformaInvoice> $model */
    private function nextSequence(string $model, int $companyId, string $year): int
    {
        $column = match ($model) {
            CrmInvoice::class => 'invoice_number',
            CrmQuotation::class => 'quotation_number',
            default => 'proforma_number',
        };
        $numbers = $model::query()
            ->where('company_id', $companyId)
            ->where($column, 'like', '%-'.$year.'-%')
            ->lockForUpdate()
            ->pluck($column);

        $highest = $numbers->map(fn (string $number): int => preg_match('/-(\d+)$/', $number, $matches) ? (int) $matches[1] : 0)->max() ?? 0;

        return $highest + 1;
    }

    private function format(string $prefix, string $documentType, string $year, int $sequence): string
    {
        // Preserve the legacy quotation format until a tenant deliberately changes its prefix.
        if ($documentType === 'QUO' && $prefix === 'RPQ') {
            return sprintf('RPQ-%s-%06d', $year, $sequence);
        }
        if ($documentType === 'PI' && $prefix === 'RPI') {
            return sprintf('RPI-%s-%06d', $year, $sequence);
        }

        return sprintf('%s-%s-%s-%05d', $prefix, $documentType, $year, $sequence);
    }
}
