<?php

namespace App\Models\Purchases;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockLocation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['goods_receipt_id', 'purchase_order_item_id', 'product_id', 'stock_location_id', 'ordered_quantity', 'received_quantity', 'accepted_quantity', 'rejected_quantity', 'unit_cost', 'batch_number', 'expiry_date', 'manufacture_date', 'notes'])]
class GoodsReceiptItem extends Model
{
    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'accepted_quantity' => 'decimal:3',
            'rejected_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'expiry_date' => 'date',
            'manufacture_date' => 'date',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }
}
