<?php

namespace App\Models\Promotions;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'promotion_rule_id', 'customer_id', 'order_id', 'cart_reference', 'usage_date', 'discount_amount', 'quantity_affected', 'metadata'])]
class PromotionRuleUsage extends Model
{
    protected function casts(): array { return ['usage_date' => 'date', 'discount_amount' => 'decimal:2', 'quantity_affected' => 'decimal:3', 'metadata' => 'array']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function rule(): BelongsTo { return $this->belongsTo(PromotionRule::class, 'promotion_rule_id'); }
}
