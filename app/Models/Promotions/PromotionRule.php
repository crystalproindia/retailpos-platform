<?php

namespace App\Models\Promotions;

use App\Enums\Promotions\DiscountType;
use App\Enums\Promotions\PromotionStatus;
use App\Enums\Promotions\PromotionType;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'campaign_id', 'name', 'slug', 'description', 'promotion_type', 'discount_type', 'priority', 'stackable', 'exclusive', 'requires_coupon', 'auto_apply', 'start_at', 'end_at', 'usage_limit_total', 'usage_limit_per_customer', 'usage_limit_per_day', 'minimum_bill_amount', 'minimum_quantity', 'maximum_discount_amount', 'status', 'is_active', 'created_by', 'approved_by', 'approved_at'])]
class PromotionRule extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'promotion_type' => PromotionType::class, 'discount_type' => DiscountType::class, 'status' => PromotionStatus::class,
            'stackable' => 'boolean', 'exclusive' => 'boolean', 'requires_coupon' => 'boolean', 'auto_apply' => 'boolean', 'is_active' => 'boolean',
            'start_at' => 'datetime', 'end_at' => 'datetime', 'approved_at' => 'datetime',
            'minimum_bill_amount' => 'decimal:2', 'minimum_quantity' => 'decimal:3', 'maximum_discount_amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(PromotionCampaign::class, 'campaign_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function conditions(): HasMany { return $this->hasMany(PromotionCondition::class); }
    public function actions(): HasMany { return $this->hasMany(PromotionAction::class)->orderBy('sort_order'); }
    public function productTargets(): HasMany { return $this->hasMany(PromotionProductTarget::class); }
    public function categoryTargets(): HasMany { return $this->hasMany(PromotionCategoryTarget::class); }
    public function brandTargets(): HasMany { return $this->hasMany(PromotionBrandTarget::class); }
    public function variantTargets(): HasMany { return $this->hasMany(PromotionVariantTarget::class); }
    public function branchTargets(): HasMany { return $this->hasMany(PromotionBranchTarget::class); }
    public function channelTargets(): HasMany { return $this->hasMany(PromotionChannelTarget::class); }
    public function coupons(): HasMany { return $this->hasMany(PromotionCoupon::class); }
    public function usage(): HasMany { return $this->hasMany(PromotionRuleUsage::class); }
}
