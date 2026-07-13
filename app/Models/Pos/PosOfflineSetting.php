<?php

namespace App\Models\Pos;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'enable_offline_pos', 'enable_auto_sync', 'allow_offline_cash', 'allow_offline_manual_card', 'allow_offline_manual_upi', 'allow_offline_wallet_usage', 'allow_offline_loyalty_redemption', 'offline_stock_conflict_strategy', 'offline_data_cache_minutes', 'max_offline_bill_amount', 'max_offline_bills_before_sync'])]
class PosOfflineSetting extends Model
{
    protected function casts(): array { return ['enable_offline_pos' => 'boolean', 'enable_auto_sync' => 'boolean', 'allow_offline_cash' => 'boolean', 'allow_offline_manual_card' => 'boolean', 'allow_offline_manual_upi' => 'boolean', 'allow_offline_wallet_usage' => 'boolean', 'allow_offline_loyalty_redemption' => 'boolean', 'max_offline_bill_amount' => 'decimal:2']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
