<?php

namespace App\Services\Promotions;

use App\Models\Promotions\PromotionSettings;

class PromotionSettingsService
{
    public function settings(int $companyId): PromotionSettings
    {
        return PromotionSettings::firstOrCreate(['company_id' => $companyId], [
            'allow_stacking' => true,
            'default_priority_strategy' => 'priority_then_benefit',
            'allow_coupon_with_auto_discount' => true,
            'require_approval_for_promotions' => false,
            'show_discount_breakup_on_bill_future' => true,
        ]);
    }
}
