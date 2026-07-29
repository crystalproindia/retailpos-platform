<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_transfer_id', 'product_id', 'requested_quantity', 'dispatched_quantity', 'received_quantity'])]
class StockTransferItem extends Model
{
    protected function casts(): array { return ['requested_quantity' => 'decimal:3', 'dispatched_quantity' => 'decimal:3', 'received_quantity' => 'decimal:3']; }
    public function transfer(): BelongsTo { return $this->belongsTo(StockTransfer::class, 'stock_transfer_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
