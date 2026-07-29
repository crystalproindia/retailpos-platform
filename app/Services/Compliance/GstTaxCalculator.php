<?php

namespace App\Services\Compliance;

use Illuminate\Validation\ValidationException;

class GstTaxCalculator
{
    /** @return array{taxable_value:string,cgst:string,sgst:string,igst:string,cess:string,tax_total:string,line_total:string,treatment:string} */
    public function calculate(string $amount, string $rate, ?string $supplierState, ?string $placeOfSupply, bool $inclusive = false, string $cessRate = '0', bool $reverseCharge = false): array
    {
        $amountPaise = $this->minor($amount);
        $rateMilli = $this->milli($rate);
        $cessMilli = $this->milli($cessRate);
        if ($amountPaise < 0 || $rateMilli < 0 || $cessMilli < 0) throw ValidationException::withMessages(['tax' => 'Taxable value and tax rates cannot be negative.']);
        if (! $supplierState || ! $placeOfSupply) throw ValidationException::withMessages(['place_of_supply' => 'Supplier state and place of supply are required for GST treatment.']);
        $inclusiveDivisor = 100000 + $rateMilli + $cessMilli;
        $taxable = $inclusive ? intdiv($amountPaise * 100000 + intdiv($inclusiveDivisor, 2), $inclusiveDivisor) : $amountPaise;
        $tax = intdiv($taxable * $rateMilli + 50000, 100000);
        $cess = intdiv($taxable * $cessMilli + 50000, 100000);
        $intra = $supplierState === $placeOfSupply;
        $cgst = $intra ? intdiv($tax + 1, 2) : 0;
        $sgst = $intra ? $tax - $cgst : 0;
        $igst = $intra ? 0 : $tax;
        $total = $inclusive ? $amountPaise : $taxable + $tax + $cess;

        return ['taxable_value' => $this->decimal($taxable), 'cgst' => $this->decimal($cgst), 'sgst' => $this->decimal($sgst), 'igst' => $this->decimal($igst), 'cess' => $this->decimal($cess), 'tax_total' => $this->decimal($tax + $cess), 'line_total' => $this->decimal($total), 'treatment' => $reverseCharge ? 'reverse_charge' : ($intra ? 'intra_state' : 'inter_state')];
    }

    private function minor(string $value): int { return $this->parse($value, 2); }
    private function milli(string $value): int { return $this->parse($value, 3); }
    private function parse(string $value, int $scale): int { if (! preg_match('/^(\d+)(?:\.(\d+))?$/', trim($value), $m)) throw ValidationException::withMessages(['tax' => 'A valid non-negative decimal is required.']); return ((int) $m[1] * (10 ** $scale)) + (int) substr(str_pad($m[2] ?? '', $scale, '0'), 0, $scale); }
    private function decimal(int $minor): string { return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT); }
}
