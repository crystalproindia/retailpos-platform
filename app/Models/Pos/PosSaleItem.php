<?php

namespace App\Models\Pos;

use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'pos_sale_id', 'product_id', 'product_variant_id', 'category_id', 'brand_id_snapshot', 'product_name', 'sku', 'barcode', 'variant_label', 'hsn_sac', 'unit', 'category_name_snapshot', 'brand_name_snapshot', 'quantity', 'unit_price', 'unit_cost_snapshot', 'total_cost_snapshot', 'gross_amount', 'gross_sales_snapshot', 'net_sales_snapshot', 'gross_profit_before_discount', 'gross_profit_snapshot', 'gross_margin_before_discount_percent', 'gross_margin_percent_snapshot', 'cost_snapshot_method', 'cost_snapshot_status', 'price_source', 'discount_type', 'discount_value', 'discount_amount', 'taxable_amount', 'tax_profile_name', 'tax_rate', 'tax_components', 'tax_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'cess_amount', 'tax_treatment_snapshot', 'line_total', 'sort_order'])]
class PosSaleItem extends Model
{
    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'unit_cost_snapshot' => 'decimal:2', 'total_cost_snapshot' => 'decimal:2', 'gross_amount' => 'decimal:2', 'gross_sales_snapshot' => 'decimal:2', 'net_sales_snapshot' => 'decimal:2', 'gross_profit_before_discount' => 'decimal:2', 'gross_profit_snapshot' => 'decimal:2', 'gross_margin_before_discount_percent' => 'decimal:4', 'gross_margin_percent_snapshot' => 'decimal:4', 'discount_value' => 'decimal:3', 'discount_amount' => 'decimal:2', 'taxable_amount' => 'decimal:2', 'tax_rate' => 'decimal:3', 'tax_components' => 'array', 'tax_amount' => 'decimal:2', 'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2', 'igst_amount' => 'decimal:2', 'cess_amount' => 'decimal:2', 'line_total' => 'decimal:2']; }
    public function sale(): BelongsTo { return $this->belongsTo(PosSale::class, 'pos_sale_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(Product::class, 'product_variant_id'); }
    public function category(): BelongsTo { return $this->belongsTo(InventoryCategory::class); }
}
