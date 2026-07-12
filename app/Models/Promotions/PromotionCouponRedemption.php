<?php

namespace App\Models\Promotions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'promotion_coupon_id', 'promotion_rule_id', 'customer_id', 'order_id', 'cart_reference', 'redeemed_by', 'discount_amount', 'redeemed_at', 'metadata'])]
class PromotionCouponRedemption extends Model
{
    protected function casts(): array { return ['discount_amount' => 'decimal:2', 'redeemed_at' => 'datetime', 'metadata' => 'array']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function coupon(): BelongsTo { return $this->belongsTo(PromotionCoupon::class, 'promotion_coupon_id'); }
    public function rule(): BelongsTo { return $this->belongsTo(PromotionRule::class, 'promotion_rule_id'); }
    public function redeemedBy(): BelongsTo { return $this->belongsTo(User::class, 'redeemed_by'); }
}
