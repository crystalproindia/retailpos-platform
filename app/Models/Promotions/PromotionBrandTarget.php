<?php

namespace App\Models\Promotions;

use App\Models\Company;
use App\Models\Inventory\InventoryBrand;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'promotion_rule_id', 'brand_id', 'include_or_exclude'])]
class PromotionBrandTarget extends Model
{
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function rule(): BelongsTo { return $this->belongsTo(PromotionRule::class, 'promotion_rule_id'); }
    public function brand(): BelongsTo { return $this->belongsTo(InventoryBrand::class); }
}
