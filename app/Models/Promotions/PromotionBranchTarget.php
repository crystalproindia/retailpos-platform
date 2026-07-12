<?php

namespace App\Models\Promotions;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'promotion_rule_id', 'branch_id', 'include_or_exclude'])]
class PromotionBranchTarget extends Model
{
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function rule(): BelongsTo { return $this->belongsTo(PromotionRule::class, 'promotion_rule_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}
