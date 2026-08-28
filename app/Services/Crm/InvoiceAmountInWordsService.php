<?php

namespace App\Services\Crm;

use App\Support\Finance\FinanceAmount;

class InvoiceAmountInWordsService
{
    /**
     * This is presentation-only. Financial values remain owned by the invoice
     * calculation services and are passed here after their authoritative total
     * has been established.
     */
    public function format(string $currency, string|int|float $amount): string
    {
        $minor = FinanceAmount::minor($amount);
        $whole = intdiv($minor, 100);
        $fraction = $minor % 100;
        $unit = strtoupper($currency) === 'INR' ? 'Rupees' : strtoupper($currency);
        $subunit = strtoupper($currency) === 'INR' ? 'Paise' : 'Cents';

        return trim($unit.' '.$this->whole($whole).' and '.$this->whole($fraction).' '.$subunit.' only');
    }

    private function whole(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $scales = [10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand', 100 => 'Hundred'];
        $parts = [];

        foreach ($scales as $value => $label) {
            if ($number >= $value) {
                $parts[] = $this->whole(intdiv($number, $value)).' '.$label;
                $number %= $value;
            }
        }

        if ($number > 0) {
            $parts[] = $this->underOneHundred($number);
        }

        return implode(' ', $parts);
    }

    private function underOneHundred(int $number): string
    {
        $small = [0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'];
        $tens = [20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'];

        if ($number < 20) {
            return $small[$number];
        }

        return $tens[intdiv($number, 10) * 10].($number % 10 ? ' '.$small[$number % 10] : '');
    }
}
