<?php

namespace App\Services\Pos;

use App\Models\Pos\PosSale;

class PosNumberService
{
    public function next(int $companyId, ?string $prefix = null): string
    {
        $prefix = strtoupper($prefix ?: 'POS').'-'.now()->format('ymd').'-';
        $count = PosSale::query()->where('company_id', $companyId)->where('sale_number', 'like', $prefix.'%')->lockForUpdate()->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
