<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\Compliance\GstSetting;
use App\Services\Compliance\GstTaxCalculator;

class SaasInvoiceTaxService
{
    public function __construct(private readonly GstTaxCalculator $taxes) {}

    /** @return array{taxable_value:string,cgst:string,sgst:string,igst:string,cess:string,tax_total:string,line_total:string,treatment:string,supplier_gstin:?string,supplier_state:?string,place_of_supply:?string,reverse_charge:bool} */
    public function calculate(Company $company, string $amount, string $rate, ?string $placeOfSupply = null, string $cessRate = '0', bool $reverseCharge = false): array
    {
        $settings = GstSetting::query()->where('company_id', $company->id)->first();
        $supplierState = $settings?->state_code;
        $placeOfSupply ??= $settings?->default_place_of_supply_state_code;

        if ($this->minor($rate) === 0 || ! $supplierState || ! $placeOfSupply) {
            return [
                'taxable_value' => $this->decimal($amount), 'cgst' => '0.00', 'sgst' => '0.00', 'igst' => '0.00',
                'cess' => '0.00', 'tax_total' => '0.00', 'line_total' => $this->decimal($amount),
                'treatment' => $reverseCharge ? 'reverse_charge' : 'unconfigured', 'supplier_gstin' => $settings?->gstin,
                'supplier_state' => $supplierState, 'place_of_supply' => $placeOfSupply, 'reverse_charge' => $reverseCharge,
            ];
        }

        return $this->taxes->calculate($amount, $rate, $supplierState, $placeOfSupply, false, $cessRate, $reverseCharge) + [
            'supplier_gstin' => $settings?->gstin,
            'supplier_state' => $supplierState,
            'place_of_supply' => $placeOfSupply,
            'reverse_charge' => $reverseCharge,
        ];
    }

    private function minor(string $value): int
    {
        return (int) round(((float) $value) * 1000);
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
