<?php

namespace App\Models\Pos;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'return_window_days', 'receipt_required', 'manager_approval_required', 'cashiers_may_initiate', 'refund_original_method_only', 'store_credit_allowed', 'damaged_may_restock', 'anonymous_returns_allowed', 'approval_threshold'])]
class PosReturnSetting extends Model
{
    protected function casts(): array
    {
        return ['receipt_required' => 'boolean', 'manager_approval_required' => 'boolean', 'cashiers_may_initiate' => 'boolean', 'refund_original_method_only' => 'boolean', 'store_credit_allowed' => 'boolean', 'damaged_may_restock' => 'boolean', 'anonymous_returns_allowed' => 'boolean', 'approval_threshold' => 'decimal:2'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
