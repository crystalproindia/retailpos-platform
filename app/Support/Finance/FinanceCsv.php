<?php

namespace App\Support\Finance;

final class FinanceCsv
{
    public static function cell(mixed $value): string
    {
        $value = (string) $value;
        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $value = "'".$value;
        }

        return $value;
    }
}
