<?php

namespace App\Services\Inventory;

use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Throwable;

class BarcodeRenderer
{
    public function svg(string $value, string $format = 'CODE128'): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $type = strtoupper($format) === 'EAN13' && preg_match('/^\d{12,13}$/', $value)
            ? BarcodeGenerator::TYPE_EAN_13
            : BarcodeGenerator::TYPE_CODE_128;

        try {
            return (new BarcodeGeneratorSVG)->getBarcode($value, $type, 1.6, 44);
        } catch (Throwable) {
            return (new BarcodeGeneratorSVG)->getBarcode($value, BarcodeGenerator::TYPE_CODE_128, 1.6, 44);
        }
    }
}
