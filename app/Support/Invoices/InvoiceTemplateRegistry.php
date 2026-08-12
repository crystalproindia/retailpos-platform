<?php

namespace App\Support\Invoices;

class InvoiceTemplateRegistry
{
    public const FORMATS = ['a4', 'a5', 'thermal_80', 'thermal_58'];

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
            'description' => $description,
            'businesses' => $description,
            'view' => $view,
            'orientations' => $orientations,
            'variant' => $variant,
        ];
    }
}
