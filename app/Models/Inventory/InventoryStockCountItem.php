<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inventory_stock_count_id', 'product_id', 'stock_location_id', 'system_quantity', 'counted_quantity', 'variance_quantity', 'unit_cost', 'notes', 'counted_at', 'counted_by'])]
class InventoryStockCountItem extends Model
{
    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'variance_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'counted_at' => 'datetime',
        ];
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(InventoryStockCount::class, 'inventory_stock_count_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
