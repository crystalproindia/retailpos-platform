<?php

namespace App\Services\Pos;

use App\Models\Pos\PosBillingSetting;

class PosBillingSettingsService
{
    public function settings(int $companyId): PosBillingSetting
    {
        return PosBillingSetting::firstOrCreate(['company_id' => $companyId], [
            'require_open_session' => true,
            'tax_inclusive_pricing' => false,
            'tax_rounding_mode' => 'half_up',
        ]);
    }
}
