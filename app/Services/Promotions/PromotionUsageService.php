<?php

namespace App\Services\Promotions;

use App\Models\Promotions\PromotionRule;
use App\Models\Promotions\PromotionRuleUsage;

class PromotionUsageService
{
    /** @param array<string, mixed> $metadata */
    public function record(PromotionRule $rule, float $discountAmount, float $quantityAffected, ?string $cartReference = null, array $metadata = []): PromotionRuleUsage
    {
        return PromotionRuleUsage::create(['company_id' => $rule->company_id, 'promotion_rule_id' => $rule->id, 'cart_reference' => $cartReference, 'usage_date' => now()->toDateString(), 'discount_amount' => $discountAmount, 'quantity_affected' => $quantityAffected, 'metadata' => $metadata]);
    }
}
