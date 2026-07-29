<?php

namespace App\Services\Compliance;

use App\Models\Compliance\GstSetting;
use App\Models\Crm\CrmInvoice;

class EinvoiceReadinessService
{
    /** @return array{eligible:bool,errors:array<int,string>,payload:array<string,mixed>|null} */
    public function validate(CrmInvoice $invoice, GstSetting $settings): array
    {
        $errors = [];
        if (! $settings->e_invoice_applicable) $errors[] = 'E-invoice applicability has not been enabled for this GST registration.';
        if (! $settings->gstin) $errors[] = 'Supplier GSTIN is missing.';
        if (! $settings->state_code) $errors[] = 'Supplier state code is missing.';
        if (! $invoice->place_of_supply_state_code) $errors[] = 'Place of supply is missing.';
        if ($invoice->items->contains(fn ($item) => ! $item->hsn_sac)) $errors[] = 'One or more invoice lines are missing HSN or SAC classification.';
        if ($invoice->status?->value !== 'issued') $errors[] = 'Only issued invoices can be validated for e-invoice readiness.';

        return ['eligible' => $errors === [], 'errors' => $errors, 'payload' => $errors ? null : ['document_number' => $invoice->invoice_number, 'document_date' => $invoice->issue_date?->format('d/m/Y'), 'supplier_gstin' => $settings->gstin, 'place_of_supply' => $invoice->place_of_supply_state_code, 'total_value' => $invoice->grand_total, 'currency' => $invoice->currency]];
    }
}
