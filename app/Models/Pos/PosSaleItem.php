<?php

namespace App\Models\Pos;

use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'pos_sale_id', 'product_id', 'category_id', 'product_name', 'sku', 'barcode', 'quantity', 'unit_price', 'discount_amount', 'tax_amount', 'line_total'])]
class PosSaleItem extends Model
{
    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'line_total' => 'decimal:2']; }
    public function sale(): BelongsTo { return $this->belongsTo(PosSale::class, 'pos_sale_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function category(): BelongsTo { return $this->belongsTo(InventoryCategory::class); }
}
