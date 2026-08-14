<?php

namespace App\Models\Purchases;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'purchase_invoice_id', 'purchase_order_id', 'goods_receipt_item_id', 'purchase_invoice_item_id', 'type', 'status', 'expected_quantity', 'actual_quantity', 'expected_amount', 'actual_amount', 'details', 'resolved_by', 'resolved_at', 'resolution_notes'])]
class PurchaseInvoiceMatchException extends Model
{
    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'actual_quantity' => 'decimal:3',
            'expected_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
