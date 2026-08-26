<?php

namespace App\Support\Finance;

final class FinanceAmount
{
    public static function minor(string|int|float|null $value): int
    {
        $value = trim((string) ($value ?? 0));
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = preg_replace('/\D/', '', $fraction) ?: '';
        $minor = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        if (isset($fraction[2]) && $fraction[2] >= '5') {
            $minor++;
        }

        return $negative ? -$minor : $minor;
    }

    public static function decimal(int $minor): string
    {
        $negative = $minor < 0;
        $digits = str_pad((string) abs($minor), 3, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);
    }
}
