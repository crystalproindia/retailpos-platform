<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'branch_id', 'warehouse_id', 'product_id', 'current_stock', 'available_stock', 'reorder_point', 'suggested_quantity', 'stockout_risk_level', 'estimated_stockout_date', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'dismissed_by', 'dismissed_at'])]
class ReorderSuggestion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_DISMISSED = 'dismissed';

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:3',
            'available_stock' => 'decimal:3',
            'reorder_point' => 'decimal:3',
            'suggested_quantity' => 'decimal:3',
            'estimated_stockout_date' => 'date',
            'reviewed_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function dismissor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }
}
