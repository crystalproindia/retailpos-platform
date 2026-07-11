<?php

namespace App\Models\Inventory;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'description', 'industry_type', 'paper_size', 'label_width_mm', 'label_height_mm', 'columns', 'rows', 'gap_horizontal_mm', 'gap_vertical_mm', 'margin_top_mm', 'margin_right_mm', 'margin_bottom_mm', 'margin_left_mm', 'barcode_type', 'barcode_width_mm', 'barcode_height_mm', 'font_size', 'show_product_name', 'show_sku', 'show_barcode_text', 'show_price', 'show_mrp', 'show_offer_price', 'show_brand', 'show_category', 'show_size', 'show_color', 'show_batch', 'show_expiry', 'show_company_name', 'show_logo', 'custom_css', 'is_default', 'is_active'])]
class BarcodeLabelTemplate extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'label_width_mm' => 'decimal:2',
            'label_height_mm' => 'decimal:2',
            'gap_horizontal_mm' => 'decimal:2',
            'gap_vertical_mm' => 'decimal:2',
            'margin_top_mm' => 'decimal:2',
            'margin_right_mm' => 'decimal:2',
            'margin_bottom_mm' => 'decimal:2',
            'margin_left_mm' => 'decimal:2',
            'barcode_width_mm' => 'decimal:2',
            'barcode_height_mm' => 'decimal:2',
            'show_product_name' => 'boolean',
            'show_sku' => 'boolean',
            'show_barcode_text' => 'boolean',
            'show_price' => 'boolean',
            'show_mrp' => 'boolean',
            'show_offer_price' => 'boolean',
            'show_brand' => 'boolean',
            'show_category' => 'boolean',
            'show_size' => 'boolean',
            'show_color' => 'boolean',
            'show_batch' => 'boolean',
            'show_expiry' => 'boolean',
            'show_company_name' => 'boolean',
            'show_logo' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function printBatches(): HasMany
    {
        return $this->hasMany(BarcodePrintBatch::class, 'template_id');
    }
}
