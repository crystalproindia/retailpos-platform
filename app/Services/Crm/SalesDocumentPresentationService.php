<?php

namespace App\Services\Crm;

use App\Models\Company;
use App\Models\InvoiceTemplateSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SalesDocumentPresentationService
{
    public const INVOICE = 'invoice';

    public const QUOTATION = 'quotation';

    public const PROFORMA = 'proforma';

    /** @var array<string, string> */
    public const INVOICE_DOCUMENT_TITLES = [
        'invoice' => 'INVOICE',
        'tax_invoice' => 'TAX INVOICE',
        'gst_invoice' => 'GST INVOICE',
        'sales_invoice' => 'SALES INVOICE',
        'commercial_invoice' => 'COMMERCIAL INVOICE',
        'custom' => 'Custom',
    ];

    public function __construct(private readonly InvoiceWatermarkService $watermarks) {}

    /** @return array{payment_details_snapshot:?array,watermark_path_snapshot:?string,document_title_snapshot:?string,presentation_snapshot_at:Carbon} */
    public function snapshot(Company $company, string $documentType): array
    {
        $setting = InvoiceTemplateSetting::query()->where('company_id', $company->id)->first();

        return [
            'payment_details_snapshot' => $this->paymentDetailsEnabled($setting, $documentType)
                ? $this->paymentDetails($setting)
                : null,
            'watermark_path_snapshot' => $setting?->watermark_enabled ? $setting->watermark_path : null,
            'document_title_snapshot' => $documentType === self::INVOICE ? $this->configuredInvoiceTitle($setting) : null,
            'presentation_snapshot_at' => now(),
        ];
    }

    /** @return array{document_title:string,payment_details:?array,watermark:array{path:?string,data_uri:?string,enabled:bool,opacity:float,position:string}} */
    public function forDocument(
        Model $document,
        string $documentType,
        ?InvoiceTemplateSetting $previewSetting = null,
        bool $useLiveSettings = false,
    ): array {
        if (! $document->exists || $useLiveSettings) {
            $setting = $previewSetting ?? InvoiceTemplateSetting::query()->where('company_id', $document->company_id)->first();
            $paymentDetails = $this->paymentDetailsEnabled($setting, $documentType) ? $this->paymentDetails($setting) : null;
            $watermarkPath = $setting?->watermark_enabled ? $setting->watermark_path : null;
            $documentTitle = $documentType === self::INVOICE
                ? $this->configuredInvoiceTitle($setting)
                : $this->fixedDocumentTitle($documentType);
        } else {
            $paymentDetails = $document->presentation_snapshot_at ? $document->payment_details_snapshot : null;
            $watermarkPath = $document->presentation_snapshot_at ? $document->watermark_path_snapshot : null;
            $documentTitle = $documentType === self::INVOICE
                ? ($this->storedInvoiceTitle($document) ?? $this->legacyInvoiceTitle($document))
                : $this->fixedDocumentTitle($documentType);
        }

        $watermarkData = $this->watermarks->dataUri($watermarkPath);

        return [
            'document_title' => $documentTitle,
            'payment_details' => $paymentDetails ?: null,
            'watermark' => [
                'path' => $watermarkPath,
                'data_uri' => $watermarkData,
                'enabled' => $watermarkData !== null,
                'opacity' => 0.12,
                'position' => 'center',
            ],
        ];
    }

    public function configuredInvoiceTitle(?InvoiceTemplateSetting $setting): string
    {
        $options = $setting?->options ?? [];
        $selection = (string) ($options['document_title'] ?? 'invoice');

        if ($selection === 'custom') {
            $title = trim((string) ($options['custom_document_title'] ?? ''));

            if ($title !== '' && mb_strlen($title) <= 60 && ! preg_match('/[<>\\p{Cc}]/u', $title)) {
                return $title;
            }
        }

        return self::INVOICE_DOCUMENT_TITLES[$selection] ?? self::INVOICE_DOCUMENT_TITLES['invoice'];
    }

    private function storedInvoiceTitle(Model $document): ?string
    {
        $title = trim((string) ($document->document_title_snapshot ?? ''));

        return $title !== '' ? $title : null;
    }

    private function legacyInvoiceTitle(Model $document): string
    {
        return $document->tax_mode !== DocumentTaxModeService::NO_GST ? 'TAX INVOICE' : 'INVOICE';
    }

    private function fixedDocumentTitle(string $documentType): string
    {
        return match ($documentType) {
            self::QUOTATION => 'QUOTATION',
            self::PROFORMA => 'PROFORMA INVOICE',
            default => 'INVOICE',
        };
    }

    /** @return array<string, string>|null */
    private function paymentDetails(?InvoiceTemplateSetting $setting): ?array
    {
        if (! $setting) {
            return null;
        }

        $fields = collect([
            'account_holder_name' => $setting->account_holder_name,
            'bank_name' => $setting->bank_name,
            'account_number' => $setting->account_number,
            'ifsc_code' => $setting->ifsc_code,
            'branch_name' => $setting->bank_branch_name,
            'swift_bic' => $setting->swift_bic,
            'upi_id' => $setting->upi_id,
            'payment_url' => $setting->payment_url,
            'payment_note' => $setting->payment_note,
        ])->map(fn (mixed $value): string => trim((string) $value))->filter();

        return $fields->isEmpty() ? null : $fields->all();
    }

    private function paymentDetailsEnabled(?InvoiceTemplateSetting $setting, string $documentType): bool
    {
        $options = $setting?->options ?? [];

        // Older settings predate the explicit switch and must keep their existing
        // document visibility behaviour until a tenant deliberately changes it.
        if (! ($options['payment_details_enabled'] ?? true)) {
            return false;
        }

        return match ($documentType) {
            self::QUOTATION => (bool) ($options['show_payment_details_on_quotation'] ?? false),
            self::PROFORMA => (bool) ($options['show_payment_details_on_proforma'] ?? false),
            default => (bool) ($options['show_bank_details'] ?? true),
        };
    }
}
