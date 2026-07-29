<?php

namespace App\Services\Compliance;

class GstinValidator
{
    public function isStructurallyValid(?string $gstin): bool
    {
        if ($gstin === null || $gstin === '') return true;

        return (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', strtoupper($gstin));
    }
}
