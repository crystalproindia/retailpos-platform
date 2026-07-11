<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['adjustment_id', 'product_id', 'stock_location_id', 'current_quantity', 'adjusted_quantity', 'difference', 'reason'])]
class StockAdjustmentItem extends Model
{
    protected function casts(): array
    {
        return [
            'current_quantity' => 'decimal:3',
            'adjusted_quantity' => 'decimal:3',
            'difference' => 'decimal:3',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }
}
