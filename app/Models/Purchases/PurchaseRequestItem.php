<?php

namespace App\Models\Purchases;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_request_id', 'product_id', 'supplier_id', 'requested_quantity', 'approved_quantity', 'estimated_price', 'expected_by', 'notes'])]
class PurchaseRequestItem extends Model
{
    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'approved_quantity' => 'decimal:3',
            'estimated_price' => 'decimal:2',
            'expected_by' => 'date',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
