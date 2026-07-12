<?php

namespace App\Models\Promotions;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'allow_stacking', 'default_priority_strategy', 'allow_coupon_with_auto_discount', 'max_discount_percentage_per_bill', 'max_discount_amount_per_bill', 'require_approval_for_promotions', 'require_approval_above_discount_percentage', 'require_approval_above_discount_amount', 'show_discount_breakup_on_bill_future'])]
class PromotionSettings extends Model
{
    protected $table = 'promotion_settings';
    protected function casts(): array { return ['allow_stacking' => 'boolean', 'allow_coupon_with_auto_discount' => 'boolean', 'max_discount_percentage_per_bill' => 'decimal:3', 'max_discount_amount_per_bill' => 'decimal:2', 'require_approval_for_promotions' => 'boolean', 'require_approval_above_discount_percentage' => 'decimal:3', 'require_approval_above_discount_amount' => 'decimal:2', 'show_discount_breakup_on_bill_future' => 'boolean']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
