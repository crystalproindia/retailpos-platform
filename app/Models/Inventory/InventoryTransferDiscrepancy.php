<?php

namespace App\Models\Inventory;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'stock_transfer_id', 'stock_transfer_item_id', 'inventory_transfer_receipt_id', 'type', 'reason', 'expected_quantity', 'actual_quantity', 'discrepancy_quantity', 'status', 'resolution', 'notes', 'reported_by', 'resolved_by', 'resolved_at'])]
class InventoryTransferDiscrepancy extends Model
{
    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'actual_quantity' => 'decimal:3',
            'discrepancy_quantity' => 'decimal:3',
            'resolved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function transferItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferItem::class, 'stock_transfer_item_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(InventoryTransferReceipt::class, 'inventory_transfer_receipt_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
