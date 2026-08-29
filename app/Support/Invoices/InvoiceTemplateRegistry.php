<?php

namespace App\Support\Invoices;

class InvoiceTemplateRegistry
{
    public const FORMATS = ['a4', 'a5', 'thermal_80', 'thermal_58'];

    /**
     * Customer downloads intentionally support a curated A4-only set. Print
     * settings retain their wider A4, A5, and thermal catalogue.
     */
    public const DOWNLOAD_PDF_KEYS = [
        'retailpos_premium_blue',
        'executive_navy_receivable',
        'modern_minimal_receivable',
        'professional_indigo_receivable',
        'emerald_finance_receivable',
        'slate_professional_receivable',
        'royal_blue_services_receivable',
        'warm_corporate_receivable',
        'compact_ledger_pro_receivable',
        'structured_gst_grid',
    ];

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        return [
            // The original keys remain stable for existing companies and historical settings.
            'structured_gst_grid' => $this->definition('Classic GST', 'a4', 'classic', 'detailed', 'Wholesale, distribution and accountant-ready GST invoices', 'invoice-templates.structured-gst-grid', ['portrait', 'landscape'], 'classic'),
            'premium_elegant' => $this->definition('Premium Gradient', 'a4', 'premium', 'summary', 'Fashion, jewellery and premium retail', 'invoice-templates.premium-elegant', ['portrait'], 'premium'),
            'compact_detailed_gst' => $this->definition('Compact Retail', 'a4', 'retail', 'detailed', 'High-volume retail and trading', 'invoice-templates.compact-detailed-gst', ['portrait', 'landscape'], 'compact'),
            'modern_split_panel' => $this->definition('Contemporary Split', 'a4', 'modern', 'summary', 'Agencies, consultants and modern brands', 'invoice-templates.modern-split-panel', ['portrait'], 'split'),
            'executive_corporate_gst' => $this->definition('Executive Navy', 'a4', 'corporate', 'detailed', 'B2B, multi-branch and GST-intensive businesses', 'invoice-templates.executive-corporate-gst', ['portrait', 'landscape'], 'executive'),

            'modern_blue_corporate' => $this->definition('Modern Blue Corporate', 'a4', 'corporate', 'detailed', 'Professional B2B and service invoices', 'invoice-templates.layouts.a4-corporate', ['portrait', 'landscape'], 'blue'),
            'bold_retail' => $this->definition('Bold Retail', 'a4', 'retail', 'summary', 'Stores that need strong totals and product hierarchy', 'invoice-templates.layouts.a4-retail', ['portrait'], 'bold'),
            'minimal_professional' => $this->definition('Minimal Professional', 'a4', 'minimal', 'summary', 'Agencies, professional services and clean correspondence', 'invoice-templates.layouts.a4-minimal', ['portrait'], 'minimal'),
            'modern_orange' => $this->definition('Modern Orange', 'a4', 'modern', 'detailed', 'Contemporary sales and distribution teams', 'invoice-templates.layouts.a4-retail', ['portrait'], 'orange'),
            'dark_header' => $this->definition('Dark Header', 'a4', 'corporate', 'detailed', 'Modern businesses with a strong document masthead', 'invoice-templates.layouts.a4-corporate', ['portrait'], 'dark'),
            'green_business' => $this->definition('Green Business', 'a4', 'retail', 'detailed', 'Grocery, pharmacy, organic and general retail', 'invoice-templates.layouts.a4-corporate', ['portrait'], 'green'),
            'elegant_purple' => $this->definition('Elegant Purple', 'a4', 'premium', 'summary', 'Boutique, beauty and premium services', 'invoice-templates.layouts.a4-minimal', ['portrait'], 'purple'),
            'corporate_split' => $this->definition('Corporate Split', 'a4', 'corporate', 'detailed', 'Structured commercial and B2B services', 'invoice-templates.layouts.a4-corporate', ['portrait', 'landscape'], 'split'),
            'premium_business' => $this->definition('Premium Business', 'a4', 'corporate', 'summary', 'Executive client billing and account services', 'invoice-templates.layouts.a4-corporate', ['portrait'], 'premium'),
            'commercial_services' => $this->definition('Commercial Services', 'a4', 'professional', 'detailed', 'Managed services, consulting and retainers', 'invoice-templates.layouts.a4-service', ['portrait'], 'commercial'),
            'consultation_minimal' => $this->definition('Consultation Minimal', 'a4', 'professional', 'summary', 'Consultations, clinics and advisory work', 'invoice-templates.layouts.a4-service', ['portrait'], 'consultation'),
            'client_billing_modern' => $this->definition('Client Billing Modern', 'a4', 'professional', 'summary', 'Professional client billing and statements', 'invoice-templates.layouts.a4-service', ['portrait'], 'client'),
            'freelancer_blue' => $this->definition('Freelancer Blue', 'a4', 'professional', 'summary', 'Independent professionals and field services', 'invoice-templates.layouts.a4-service', ['portrait'], 'freelancer'),
            'creative_studio' => $this->definition('Creative Studio', 'a4', 'creative', 'summary', 'Artist, design and creative services', 'invoice-templates.layouts.a4-creative', ['portrait'], 'studio'),
            'licensing_premium' => $this->definition('Licensing Premium', 'a4', 'creative', 'detailed', 'Licensing, royalty and intellectual-property billing', 'invoice-templates.layouts.a4-creative', ['portrait'], 'licensing'),
            'publishing_royalty' => $this->definition('Publishing Royalty', 'a4', 'creative', 'summary', 'Book, content and publishing settlements', 'invoice-templates.layouts.a4-creative', ['portrait'], 'publishing'),
            'construction_blue' => $this->definition('Construction Blue', 'a4', 'industry', 'detailed', 'Construction milestones and site services', 'invoice-templates.layouts.a4-industry', ['portrait'], 'construction'),
            'contractor_red' => $this->definition('Contractor Red', 'a4', 'industry', 'summary', 'Contractors, repairs and project work', 'invoice-templates.layouts.a4-industry', ['portrait'], 'contractor'),
            'medical_consultation' => $this->definition('Medical Consultation', 'a4', 'industry', 'summary', 'Clinics, diagnostics and consultation services', 'invoice-templates.layouts.a4-industry', ['portrait'], 'medical'),
            'catering_modern' => $this->definition('Catering Modern', 'a4', 'industry', 'summary', 'Catering, events and service packages', 'invoice-templates.layouts.a4-industry', ['portrait'], 'catering'),
            'rental_orange' => $this->definition('Rental Orange', 'a4', 'industry', 'detailed', 'Rental, hire and equipment billing', 'invoice-templates.layouts.a4-industry', ['portrait'], 'rental'),
            'retailpos_premium_blue' => $this->definition('RetailPOS Premium Blue', 'a4', 'corporate', 'detailed', 'Premium corporate invoice with prominent payment and receivable summary', 'invoice-templates.layouts.premium.retailpos-premium-blue', ['portrait'], 'premium_blue'),
            'executive_navy_receivable' => $this->definition('Executive Navy', 'a4', 'corporate', 'detailed', 'Boardroom-ready enterprise billing and receivable summary', 'invoice-templates.layouts.premium.executive-navy', ['portrait'], 'executive_navy'),
            'modern_minimal_receivable' => $this->definition('Modern Minimal', 'a4', 'minimal', 'summary', 'Quiet, Apple-like business invoice with refined receivable cells', 'invoice-templates.layouts.premium.modern-minimal', ['portrait'], 'modern_minimal'),
            'professional_indigo_receivable' => $this->definition('Professional Indigo', 'a4', 'modern', 'detailed', 'SaaS and services invoice with focused balance hierarchy', 'invoice-templates.layouts.premium.professional-indigo', ['portrait'], 'professional_indigo'),
            'emerald_finance_receivable' => $this->definition('Emerald Finance', 'a4', 'corporate', 'detailed', 'Formal financial invoice with clear collection information', 'invoice-templates.layouts.premium.emerald-finance', ['portrait'], 'emerald_finance'),
            'slate_professional_receivable' => $this->definition('Slate Professional', 'a4', 'minimal', 'summary', 'Neutral print-friendly business ledger presentation', 'invoice-templates.layouts.premium.slate-professional', ['portrait'], 'slate_professional'),
            'royal_blue_services_receivable' => $this->definition('Royal Blue Services', 'a4', 'professional', 'detailed', 'Long-description service invoice with project-friendly hierarchy', 'invoice-templates.layouts.premium.royal-blue-services', ['portrait'], 'royal_blue_services'),
            'warm_corporate_receivable' => $this->definition('Warm Corporate', 'a4', 'premium', 'summary', 'Friendly premium document with restrained copper accents', 'invoice-templates.layouts.premium.warm-corporate', ['portrait'], 'warm_corporate'),
            'compact_ledger_pro_receivable' => $this->definition('Compact Ledger Pro', 'a4', 'classic', 'detailed', 'Dense but readable multi-page invoice and receivable ledger', 'invoice-templates.layouts.premium.compact-ledger-pro', ['portrait'], 'compact_ledger_pro'),
            'a5_consultation' => $this->definition('A5 Consultation', 'a5', 'professional', 'summary', 'Compact professional consultations', 'invoice-templates.layouts.a5', ['portrait'], 'consultation'),
            'a5_creative' => $this->definition('A5 Creative', 'a5', 'creative', 'summary', 'Compact artist and studio billing', 'invoice-templates.layouts.a5', ['portrait'], 'creative'),

            'a5_modern_retail' => $this->definition('A5 Modern Retail', 'a5', 'retail', 'summary', 'Retail and delivery invoices', 'invoice-templates.layouts.a5', ['portrait'], 'modern'),
            'a5_compact_gst' => $this->definition('A5 Compact GST', 'a5', 'classic', 'detailed', 'Compact GST tax invoices', 'invoice-templates.layouts.a5', ['portrait'], 'gst'),
            'a5_boutique' => $this->definition('A5 Boutique', 'a5', 'premium', 'summary', 'Boutique, beauty and fashion', 'invoice-templates.layouts.a5', ['portrait'], 'boutique'),
            'a5_professional' => $this->definition('A5 Professional', 'a5', 'corporate', 'detailed', 'Small B2B and professional billing', 'invoice-templates.layouts.a5', ['portrait'], 'professional'),
            'a5_bold' => $this->definition('A5 Bold', 'a5', 'retail', 'summary', 'Fast retail counter invoices', 'invoice-templates.layouts.a5', ['portrait'], 'bold'),
            'a5_minimal' => $this->definition('A5 Minimal', 'a5', 'minimal', 'summary', 'Simple service and delivery invoices', 'invoice-templates.layouts.a5', ['portrait'], 'minimal'),
            'a5_service_invoice' => $this->definition('A5 Service Invoice', 'a5', 'modern', 'detailed', 'Repairs, services and field teams', 'invoice-templates.layouts.a5', ['portrait'], 'service'),

            'thermal_80_classic' => $this->definition('Thermal Classic', 'thermal_80', 'classic', 'summary', 'Clear POS receipt with core billing details', 'invoice-templates.layouts.thermal', [], 'classic'),
            'thermal_80_modern' => $this->definition('Thermal Modern', 'thermal_80', 'modern', 'summary', 'Clean branded POS receipt', 'invoice-templates.layouts.thermal', [], 'modern'),
            'thermal_80_compact' => $this->definition('Thermal Compact', 'thermal_80', 'minimal', 'summary', 'Fast, dense counter receipt', 'invoice-templates.layouts.thermal', [], 'compact'),
            'thermal_80_gst_detailed' => $this->definition('Thermal GST Detailed', 'thermal_80', 'classic', 'detailed', 'Receipt with HSN and GST breakdown', 'invoice-templates.layouts.thermal', [], 'gst'),

            'thermal_58_mini' => $this->definition('Thermal Mini', 'thermal_58', 'minimal', 'summary', 'Short receipt for compact printers', 'invoice-templates.layouts.thermal', [], 'mini'),
            'thermal_58_essential' => $this->definition('Thermal Essential', 'thermal_58', 'modern', 'summary', 'Essential POS information at 58mm', 'invoice-templates.layouts.thermal', [], 'essential'),
            'thermal_58_gst_compact' => $this->definition('Thermal GST Compact', 'thermal_58', 'classic', 'detailed', 'Compact GST receipt with readable totals', 'invoice-templates.layouts.thermal', [], 'gst_compact'),
            'thermal_80_service' => $this->definition('Thermal Service', 'thermal_80', 'professional', 'summary', 'Service counter receipt with customer balance', 'invoice-templates.layouts.thermal', [], 'service'),
            'thermal_58_retail' => $this->definition('Thermal Retail', 'thermal_58', 'retail', 'summary', 'Fast retail receipt with readable totals', 'invoice-templates.layouts.thermal', [], 'retail'),
        ];
    }

    /** @return array<string,mixed> */
    public function find(string $key): array
    {
        return $this->all()[$key] ?? $this->all()['structured_gst_grid'];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /** @return array<string,array<string,mixed>> */
    public function forFormat(string $format): array
    {
        return array_filter($this->all(), fn (array $definition): bool => $definition['paper_format'] === $format);
    }

    public function isCompatible(string $key, string $format): bool
    {
        return ($this->all()[$key]['paper_format'] ?? null) === $format;
    }

    public function defaultFor(string $format): string
    {
        return array_key_first($this->forFormat($format)) ?? 'structured_gst_grid';
    }

    /** @return array<string,array<string,mixed>> */
    public function downloadPdfDesigns(): array
    {
        $all = $this->all();
        $designs = [];

        foreach (self::DOWNLOAD_PDF_KEYS as $key) {
            if (($all[$key]['paper_format'] ?? null) === 'a4') {
                $designs[$key] = $all[$key];
            }
        }

        return $designs;
    }

    public function isDownloadPdfDesign(string $key): bool
    {
        return array_key_exists($key, $this->downloadPdfDesigns());
    }

    public function defaultDownloadPdfDesign(): string
    {
        return 'retailpos_premium_blue';
    }

    /** @return list<string> */
    public function orientations(string $key, string $format): array
    {
        return $this->isCompatible($key, $format) ? $this->find($key)['orientations'] : [];
    }

    /** @return array<string,mixed> */
    private function definition(string $label, string $paperFormat, string $style, string $gstDetail, string $description, string $view, array $orientations, string $variant): array
    {
        return [
            'label' => $label,
            'name' => $label,
            'paper_format' => $paperFormat,
            'style' => $style,
            'gst_detail' => $gstDetail,
            'tax_modes' => ['gst', 'no_gst'],
            'supports_signature' => $paperFormat !== 'thermal_58',
            'description' => $description,
            'businesses' => $description,
            'view' => $view,
            'orientations' => $orientations,
            'variant' => $variant,
        ];
    }
}
