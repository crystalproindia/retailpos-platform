<?php

namespace App\Services\Crm;

use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\InvoiceTemplateSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Branding\CompanyBrandingService;
use App\Support\Invoices\InvoiceTemplateRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class InvoiceTemplateService
{
    public const KEYS = [
        'structured_gst_grid', 'premium_elegant', 'compact_detailed_gst', 'modern_split_panel', 'executive_corporate_gst',
        'modern_blue_corporate', 'bold_retail', 'minimal_professional', 'modern_orange', 'dark_header', 'green_business', 'elegant_purple',
        'a5_modern_retail', 'a5_compact_gst', 'a5_boutique', 'a5_professional', 'a5_bold', 'a5_minimal', 'a5_service_invoice',
        'thermal_80_classic', 'thermal_80_modern', 'thermal_80_compact', 'thermal_80_gst_detailed',
        'thermal_58_mini', 'thermal_58_essential', 'thermal_58_gst_compact',
        'corporate_split', 'premium_business', 'commercial_services', 'consultation_minimal', 'client_billing_modern', 'freelancer_blue',
        'creative_studio', 'licensing_premium', 'publishing_royalty', 'construction_blue', 'contractor_red', 'medical_consultation',
        'catering_modern', 'rental_orange', 'a5_consultation', 'a5_creative', 'thermal_80_service', 'thermal_58_retail',
    ];

    /** @return array<string,array<string,mixed>> */
    public function definitions(): array
    {
        return $this->registry->all();
    }

    public function __construct(
        private readonly InvoiceBalancePresentationService $balances,
        private readonly InvoicePaymentQrService $paymentQr,
        private readonly CompanyBrandingService $branding,
        private readonly SalesDocumentPresentationService $presentations,
        private readonly InvoiceWatermarkService $watermarks,
        private readonly AuditLogger $audit,
        private readonly InvoiceTemplateRegistry $registry,
    ) {}

    public function setting(Company $company): InvoiceTemplateSetting
    {
        return InvoiceTemplateSetting::firstOrCreate(['company_id' => $company->id], [
            'paper_format' => 'a4',
            'gst_presentation' => 'detailed',
            'options' => $this->defaultOptions(),
        ])->refresh();
    }

    /** @return array{data_uri: ?string, source: ?string, show_logo: bool, logo_position: string, logo_size: string, show_company_name: bool} */
    public function brandingFor(Company $company, ?InvoiceTemplateSetting $setting = null): array
    {
        $setting ??= $this->setting($company);
        $options = array_replace($this->defaultOptions(), $setting->options ?? []);
        $logo = $this->branding->forInvoice($company);

        return [
            'data_uri' => $logo['data_uri'],
            'source' => $logo['source'],
            'show_logo' => (bool) $options['show_logo'],
            'logo_position' => in_array($options['logo_position'], ['left', 'center', 'right'], true) ? $options['logo_position'] : 'left',
            'logo_size' => in_array($options['logo_size'], ['small', 'medium', 'large'], true) ? $options['logo_size'] : 'medium',
            'show_company_name' => (bool) $options['show_company_name'],
        ];
    }

    /** @param array<string,mixed> $data */
    public function update(
        Company $company,
        User $user,
        array $data,
        ?UploadedFile $watermark = null,
        bool $removeWatermark = false,
    ): InvoiceTemplateSetting {
        $setting = $this->setting($company);
        $templateKey = (string) $data['template_key'];
        if (! $this->registry->has($templateKey)) {
            $templateKey = $this->registry->defaultFor('a4');
        }
        $definition = $this->registry->find($templateKey);
        $paperFormat = $this->registry->isCompatible($templateKey, (string) ($data['paper_format'] ?? $definition['paper_format']))
            ? (string) ($data['paper_format'] ?? $definition['paper_format'])
            : $definition['paper_format'];
        $orientations = $this->registry->orientations($templateKey, $paperFormat);
        $orientation = in_array($data['orientation'] ?? 'portrait', $orientations, true) ? $data['orientation'] : 'portrait';
        $previousWatermark = $setting->watermark_path;
        $newWatermark = $watermark ? $this->watermarks->store($watermark, $company->id) : null;

        try {
            DB::transaction(function () use ($setting, $data, $user, $company, $templateKey, $paperFormat, $orientation, $newWatermark, $removeWatermark, $previousWatermark): void {
                $setting->update([
                    'template_key' => $templateKey,
                    'paper_format' => $paperFormat,
                    'brand_color' => $data['brand_color'],
                    'copy_label' => $data['copy_label'],
                    'orientation' => $orientation,
                    'gst_presentation' => $data['gst_presentation'] ?? $setting->gst_presentation ?? 'detailed',
                    'payment_qr_uri' => $data['payment_qr_uri'] ?? null,
                    'account_holder_name' => array_key_exists('account_holder_name', $data) ? $data['account_holder_name'] : $setting->account_holder_name,
                    'bank_name' => array_key_exists('bank_name', $data) ? $data['bank_name'] : $setting->bank_name,
                    'account_number' => ($data['replace_account_number'] ?? array_key_exists('account_number', $data)) && array_key_exists('account_number', $data)
                        ? $data['account_number']
                        : $setting->account_number,
                    'ifsc_code' => array_key_exists('ifsc_code', $data) ? strtoupper((string) $data['ifsc_code']) ?: null : $setting->ifsc_code,
                    'bank_branch_name' => array_key_exists('bank_branch_name', $data) ? $data['bank_branch_name'] : $setting->bank_branch_name,
                    'swift_bic' => array_key_exists('swift_bic', $data) ? strtoupper((string) $data['swift_bic']) ?: null : $setting->swift_bic,
                    'upi_id' => array_key_exists('upi_id', $data) ? $data['upi_id'] : $setting->upi_id,
                    'payment_url' => array_key_exists('payment_url', $data) ? $data['payment_url'] : $setting->payment_url,
                    'payment_note' => array_key_exists('payment_note', $data) ? $data['payment_note'] : $setting->payment_note,
                    'watermark_path' => $newWatermark ?: ($removeWatermark ? null : $previousWatermark),
                    'watermark_enabled' => $removeWatermark
                        ? false
                        : (array_key_exists('watermark_enabled', $data) ? (bool) $data['watermark_enabled'] : $setting->watermark_enabled),
                    'options' => array_replace($this->defaultOptions(), $data['options'] ?? []),
                    'updated_by' => $user->id,
                ]);
                $this->audit->record('crm.invoice_template.updated', $setting, 'Invoice template settings updated.', [
                    'company_id' => $company->id,
                    'template_key' => $setting->template_key,
                    'paper_format' => $setting->paper_format,
                    'payment_details_configured' => (bool) $setting->account_number || (bool) $setting->upi_id || (bool) $setting->payment_url,
                    'watermark_enabled' => $setting->watermark_enabled,
                    'watermark_changed' => (bool) $newWatermark || $removeWatermark,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($newWatermark) {
                $this->watermarks->delete($newWatermark);
            }

            throw $exception;
        }

        if (($newWatermark || $removeWatermark) && $previousWatermark !== $newWatermark) {
            $this->watermarks->deleteIfUnreferenced($previousWatermark);
        }

        return $setting->refresh();
    }

    /** @return array<string,mixed> */
    public function renderData(CrmInvoice $invoice, array $overrides = []): array
    {
        $setting = $this->setting($invoice->company);
        if (! $this->registry->has((string) $setting->template_key)) {
            $setting = $this->previewSetting($setting, [
                'template_key' => $this->registry->defaultFor('a4'),
                'paper_format' => 'a4',
                'orientation' => 'portrait',
            ]);
        }
        if ($overrides !== []) {
            $setting = $this->previewSetting($setting, $overrides);
        }
        $items = $invoice->items;
        $rows = [];
        foreach ($items as $item) {
            $key = ($item->hsn_sac ?: 'Unclassified').'|'.$item->tax_rate.'|'.$item->tax_treatment_snapshot;
            $rows[$key] ??= ['hsn_sac' => $item->hsn_sac ?: '—', 'tax_treatment' => $item->tax_treatment_snapshot ?: 'standard', 'taxable' => 0, 'tax_rate' => (float) $item->tax_rate, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'cess' => 0];
            foreach (['taxable' => 'line_subtotal', 'cgst' => 'cgst_amount', 'sgst' => 'sgst_amount', 'igst' => 'igst_amount', 'cess' => 'cess_amount'] as $target => $field) {
                $rows[$key][$target] += (float) $item->{$field};
            }
        }
        foreach ($rows as &$row) {
            $row['cgst_rate'] = $row['cgst'] > 0 ? $row['tax_rate'] / 2 : 0;
            $row['sgst_rate'] = $row['sgst'] > 0 ? $row['tax_rate'] / 2 : 0;
            $row['igst_rate'] = $row['igst'] > 0 ? $row['tax_rate'] : 0;
            $row['total_tax'] = $row['cgst'] + $row['sgst'] + $row['igst'] + $row['cess'];
        }
        unset($row);
        $balance = $this->balances->forInvoice($invoice);
        $paymentQr = $this->paymentQr->forInvoice($invoice, $setting);

        $presentation = $this->presentations->forDocument(
            $invoice,
            SalesDocumentPresentationService::INVOICE,
            $setting,
            (bool) ($overrides['_preview_live_presentation'] ?? false),
        );
        if (($setting->paper_format ?? null) === 'thermal_58') {
            $presentation['watermark']['enabled'] = false;
            $presentation['watermark']['data_uri'] = null;
        }

        return [
            'setting' => $setting,
            'template' => $this->registry->find($setting->template_key),
            'item_chunks' => $items->chunk(50),
            'tax_rows' => array_values($rows),
            'balance' => $balance,
            'payment_qr_uri' => $paymentQr['payload'] ?? null,
            'payment_qr_data_uri' => $paymentQr['data_uri'] ?? null,
            'branding' => $this->brandingFor($invoice->company, $setting),
            'is_gst' => $invoice->tax_mode !== DocumentTaxModeService::NO_GST,
            'signature' => $this->branding->signatureForPath($invoice->signature_path_snapshot, $invoice->signatory_name_snapshot, $invoice->signatory_designation_snapshot),
            'payment_details' => $presentation['payment_details'],
            'watermark' => $presentation['watermark'],
            'document_title' => $presentation['document_title'].((int) $invoice->amendment_version > 1 ? ' · AMENDED · VERSION '.$invoice->amendment_version : ''),
        ];
    }

    /** @return array<string,bool> */
    public function defaultOptions(): array
    {
        return ['document_title' => 'invoice', 'show_logo' => true, 'logo_position' => 'left', 'logo_size' => 'medium', 'show_company_name' => true, 'show_bill_to' => true, 'show_ship_to' => false, 'payment_details_enabled' => true, 'show_bank_details' => true, 'show_payment_details_on_quotation' => false, 'show_payment_details_on_proforma' => false, 'show_terms' => true, 'show_signature' => true, 'show_seal' => false, 'show_amount_words' => true, 'show_received_amount' => true, 'show_previous_balance' => true, 'show_current_balance' => true, 'show_hsn_sac' => true, 'show_sku' => false, 'show_discount' => true, 'show_gst_breakup' => true, 'show_gst_summary' => true, 'show_payment_status' => true];
    }

    /** @param array<string,mixed> $overrides */
    private function previewSetting(InvoiceTemplateSetting $setting, array $overrides): InvoiceTemplateSetting
    {
        $preview = clone $setting;
        $key = (string) ($overrides['template_key'] ?? $setting->template_key);
        $definition = $this->registry->find($key);
        $format = (string) ($overrides['paper_format'] ?? $setting->paper_format ?? $definition['paper_format']);
        if (! $this->registry->isCompatible($key, $format)) {
            $format = $definition['paper_format'];
        }
        $orientations = $this->registry->orientations($key, $format);
        $orientation = in_array($overrides['orientation'] ?? $setting->orientation, $orientations, true) ? $overrides['orientation'] ?? $setting->orientation : 'portrait';
        $preview->forceFill([
            'template_key' => $key,
            'paper_format' => $format,
            'orientation' => $orientation,
            'brand_color' => $overrides['brand_color'] ?? $setting->brand_color,
            'copy_label' => $overrides['copy_label'] ?? $setting->copy_label,
            'gst_presentation' => $overrides['gst_presentation'] ?? $setting->gst_presentation ?? $definition['gst_detail'],
            'payment_qr_uri' => $overrides['payment_qr_uri'] ?? $setting->payment_qr_uri,
            'options' => array_replace($this->defaultOptions(), $setting->options ?? [], $overrides['options'] ?? []),
        ]);

        return $preview;
    }
}
