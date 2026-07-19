<?php

namespace App\Models\Pos;

use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'pos_sale_id', 'product_id', 'product_variant_id', 'category_id', 'product_name', 'sku', 'barcode', 'variant_label', 'hsn_sac', 'unit', 'quantity', 'unit_price', 'price_source', 'discount_type', 'discount_value', 'discount_amount', 'taxable_amount', 'tax_profile_name', 'tax_rate', 'tax_components', 'tax_amount', 'line_total', 'sort_order'])]
class PosSaleItem extends Model
{
    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_value' => 'decimal:3', 'discount_amount' => 'decimal:2', 'taxable_amount' => 'decimal:2', 'tax_rate' => 'decimal:3', 'tax_components' => 'array', 'tax_amount' => 'decimal:2', 'line_total' => 'decimal:2']; }
    public function sale(): BelongsTo { return $this->belongsTo(PosSale::class, 'pos_sale_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(Product::class, 'product_variant_id'); }
    public function category(): BelongsTo { return $this->belongsTo(InventoryCategory::class); }
}
