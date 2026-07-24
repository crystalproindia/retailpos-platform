<?php

namespace App\Services\Crm;

use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\InvoiceTemplateSetting;
use App\Models\User;

class InvoiceTemplateService
{
    public const KEYS = ['structured_gst_grid', 'premium_elegant', 'compact_detailed_gst', 'modern_split_panel', 'executive_corporate_gst'];

    /** @return array<string,array<string,mixed>> */
    public function definitions(): array
    {
        return [
            'structured_gst_grid' => ['name' => 'Structured GST Grid', 'density' => 'balanced', 'gst_detail' => 'detailed', 'businesses' => 'Wholesale, hardware, manufacturing and distribution'],
            'premium_elegant' => ['name' => 'Premium Elegant', 'density' => 'spacious', 'gst_detail' => 'summary', 'businesses' => 'Fashion, jewellery, furniture and premium retail'],
            'compact_detailed_gst' => ['name' => 'Compact Detailed GST', 'density' => 'compact', 'gst_detail' => 'detailed', 'businesses' => 'Retail, FMCG, supermarkets and trading'],
            'modern_split_panel' => ['name' => 'Modern Split Panel', 'density' => 'balanced', 'gst_detail' => 'summary', 'businesses' => 'Agencies, consultants, software and modern brands'],
            'executive_corporate_gst' => ['name' => 'Executive Corporate GST', 'density' => 'balanced', 'gst_detail' => 'detailed', 'businesses' => 'B2B, multi-branch and GST-intensive businesses'],
        ];
    }

    public function setting(Company $company): InvoiceTemplateSetting
    {
        return InvoiceTemplateSetting::firstOrCreate(['company_id' => $company->id], ['options' => $this->defaultOptions()])->refresh();
    }

    /** @param array<string,mixed> $data */
    public function update(Company $company, User $user, array $data): InvoiceTemplateSetting
    {
        $setting = $this->setting($company);
        $setting->update([
            'template_key' => $data['template_key'], 'brand_color' => $data['brand_color'], 'copy_label' => $data['copy_label'],
            'orientation' => $data['orientation'], 'payment_qr_uri' => $data['payment_qr_uri'] ?? null,
            'options' => array_replace($this->defaultOptions(), $data['options'] ?? []), 'updated_by' => $user->id,
        ]);
        return $setting->refresh();
    }

    /** @return array<string,mixed> */
    public function renderData(CrmInvoice $invoice): array
    {
        $setting = $this->setting($invoice->company);
        $items = $invoice->items;
        $rows = [];
        foreach ($items as $item) {
            $key = ($item->hsn_sac ?: 'Unclassified').'|'.$item->tax_rate.'|'.$item->tax_treatment_snapshot;
            $rows[$key] ??= ['hsn_sac' => $item->hsn_sac ?: '—', 'taxable' => 0, 'tax_rate' => (float) $item->tax_rate, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'cess' => 0];
            foreach (['taxable' => 'line_subtotal', 'cgst' => 'cgst_amount', 'sgst' => 'sgst_amount', 'igst' => 'igst_amount', 'cess' => 'cess_amount'] as $target => $field) $rows[$key][$target] += (float) $item->{$field};
        }
        foreach ($rows as &$row) {
            $row['cgst_rate'] = $row['cgst'] > 0 ? $row['tax_rate'] / 2 : 0;
            $row['sgst_rate'] = $row['sgst'] > 0 ? $row['tax_rate'] / 2 : 0;
            $row['igst_rate'] = $row['igst'] > 0 ? $row['tax_rate'] : 0;
            $row['total_tax'] = $row['cgst'] + $row['sgst'] + $row['igst'] + $row['cess'];
        }
        unset($row);
        return ['setting' => $setting, 'template' => $this->definitions()[$setting->template_key] ?? $this->definitions()['structured_gst_grid'], 'tax_rows' => array_values($rows), 'previous_balance' => null, 'current_balance' => $invoice->balance_due];
    }

    /** @return array<string,bool> */
    public function defaultOptions(): array
    {
        return ['show_logo' => true, 'show_bill_to' => true, 'show_ship_to' => false, 'show_bank_details' => true, 'show_terms' => true, 'show_signature' => true, 'show_seal' => false, 'show_amount_words' => true, 'show_received_amount' => true, 'show_previous_balance' => true, 'show_current_balance' => true, 'show_hsn_sac' => true, 'show_sku' => false, 'show_discount' => true, 'show_gst_breakup' => true, 'show_gst_summary' => true, 'show_payment_status' => true];
    }
}
