<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'branch_id', 'warehouse_id', 'stock_location_id', 'product_id', 'quantity_on_hand', 'quantity_reserved', 'quantity_available', 'reorder_point', 'reorder_quantity', 'minimum_stock', 'maximum_stock', 'safety_stock', 'preferred_supplier_id', 'supplier_lead_time_days', 'average_daily_sales', 'last_stock_movement_at'])]
class StockLevel extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'quantity_reserved' => 'decimal:3',
            'quantity_available' => 'decimal:3',
            'reorder_point' => 'decimal:3',
            'reorder_quantity' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'maximum_stock' => 'decimal:3',
            'safety_stock' => 'decimal:3',
            'average_daily_sales' => 'decimal:3',
            'last_stock_movement_at' => 'datetime',
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
