<?php

namespace App\Models\Inventory;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'stock_transfer_id', 'receipt_number', 'received_by', 'received_at', 'notes', 'idempotency_key'])]
class InventoryTransferReceipt extends Model
{
    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferReceiptItem::class);
    }
}
