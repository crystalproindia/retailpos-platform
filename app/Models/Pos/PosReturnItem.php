<?php

namespace App\Models\Pos;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pos_return_id', 'original_sale_item_id', 'product_id', 'product_variant_id', 'product_name', 'sku', 'barcode', 'variant_label', 'hsn_sac', 'unit', 'original_quantity', 'previously_returned_quantity', 'return_quantity', 'unit_price_snapshot', 'gross_adjustment', 'discount_adjustment', 'taxable_adjustment', 'tax_adjustment', 'cgst_adjustment', 'sgst_adjustment', 'igst_adjustment', 'cess_adjustment', 'line_refund_total', 'stock_disposition', 'condition_note'])]
class PosReturnItem extends Model
{
    protected function casts(): array
    {
        return array_fill_keys(['original_quantity', 'previously_returned_quantity', 'return_quantity'], 'decimal:3') + array_fill_keys(['unit_price_snapshot', 'gross_adjustment', 'discount_adjustment', 'taxable_adjustment', 'tax_adjustment', 'cgst_adjustment', 'sgst_adjustment', 'igst_adjustment', 'cess_adjustment', 'line_refund_total'], 'decimal:2');
    }
    public function posReturn(): BelongsTo { return $this->belongsTo(PosReturn::class, 'pos_return_id'); }
    public function originalSaleItem(): BelongsTo { return $this->belongsTo(PosSaleItem::class, 'original_sale_item_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
