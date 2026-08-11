<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['stock_transfer_id', 'product_id', 'source_stock_location_id', 'destination_stock_location_id', 'inventory_batch_id', 'requested_quantity', 'approved_quantity', 'packed_quantity', 'dispatched_quantity', 'in_transit_quantity', 'received_quantity', 'damaged_quantity', 'short_quantity', 'rejected_quantity', 'unit_snapshot', 'notes'])]
class StockTransferItem extends Model
{
    protected function casts(): array
    {
        return ['requested_quantity' => 'decimal:3', 'approved_quantity' => 'decimal:3', 'packed_quantity' => 'decimal:3', 'dispatched_quantity' => 'decimal:3', 'in_transit_quantity' => 'decimal:3', 'received_quantity' => 'decimal:3', 'damaged_quantity' => 'decimal:3', 'short_quantity' => 'decimal:3', 'rejected_quantity' => 'decimal:3'];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_stock_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'destination_stock_location_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(InventoryTransferDiscrepancy::class);
    }

    public function serialNumbers(): BelongsToMany
    {
        return $this->belongsToMany(InventorySerialNumber::class, 'inventory_transfer_item_serials')->withPivot('status')->withTimestamps();
    }
}
