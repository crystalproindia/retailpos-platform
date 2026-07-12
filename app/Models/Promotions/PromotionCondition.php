<?php

namespace App\Models\Promotions;

use App\Enums\Promotions\PromotionConditionType;
use App\Enums\Promotions\PromotionOperator;
use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'promotion_rule_id', 'condition_type', 'operator', 'value', 'value_json', 'sort_order'])]
class PromotionCondition extends Model
{
    protected function casts(): array { return ['condition_type' => PromotionConditionType::class, 'operator' => PromotionOperator::class, 'value_json' => 'array']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function rule(): BelongsTo { return $this->belongsTo(PromotionRule::class, 'promotion_rule_id'); }
}
