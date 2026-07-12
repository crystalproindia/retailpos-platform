<?php

namespace App\Services\Promotions;

use App\Models\Promotions\PromotionRule;
use App\Models\User;

class PromotionApprovalService
{
    public function __construct(private readonly PromotionRuleService $rules) {}
    public function approve(PromotionRule $rule, User $user): PromotionRule { return $this->rules->approve($rule, $user); }
}
