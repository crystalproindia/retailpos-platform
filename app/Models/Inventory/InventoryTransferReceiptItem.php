<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inventory_transfer_receipt_id', 'stock_transfer_item_id', 'received_quantity', 'damaged_quantity', 'short_quantity', 'notes'])]
class InventoryTransferReceiptItem extends Model
{
    protected function casts(): array
    {
        return ['received_quantity' => 'decimal:3', 'damaged_quantity' => 'decimal:3', 'short_quantity' => 'decimal:3'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(InventoryTransferReceipt::class, 'inventory_transfer_receipt_id');
    }

    public function transferItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferItem::class, 'stock_transfer_item_id');
    }
}
