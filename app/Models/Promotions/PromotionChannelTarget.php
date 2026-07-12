<?php

namespace App\Models\Promotions;

use App\Models\Company;
use App\Models\Inventory\SalesChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'promotion_rule_id', 'sales_channel_id', 'include_or_exclude'])]
class PromotionChannelTarget extends Model
{
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function rule(): BelongsTo { return $this->belongsTo(PromotionRule::class, 'promotion_rule_id'); }
    public function salesChannel(): BelongsTo { return $this->belongsTo(SalesChannel::class); }
}
