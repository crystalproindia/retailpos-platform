<?php

namespace App\Models\Promotions;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'promotion_rule_id', 'code', 'description', 'usage_limit_total', 'usage_limit_per_customer', 'used_count', 'start_at', 'end_at', 'is_active'])]
class PromotionCoupon extends Model
{
    use SoftDeletes;

    protected function casts(): array { return ['start_at' => 'datetime', 'end_at' => 'datetime', 'is_active' => 'boolean']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function rule(): BelongsTo { return $this->belongsTo(PromotionRule::class, 'promotion_rule_id'); }
    public function redemptions(): HasMany { return $this->hasMany(PromotionCouponRedemption::class); }
}
