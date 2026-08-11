<?php

namespace App\Models\Inventory;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'product_id', 'warehouse_id', 'stock_location_id', 'batch_number', 'manufactured_at', 'expires_at', 'quantity_on_hand', 'quantity_available', 'unit_cost', 'supplier_reference', 'receipt_reference', 'status'])]
class InventoryBatch extends Model
{
    protected function casts(): array
    {
        return [
            'manufactured_at' => 'date',
            'expires_at' => 'date',
            'quantity_on_hand' => 'decimal:3',
            'quantity_available' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(InventorySerialNumber::class);
    }
}
