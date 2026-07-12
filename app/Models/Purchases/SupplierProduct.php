<?php

namespace App\Models\Purchases;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\Inventory\InventoryTaxRate;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'supplier_id', 'product_id', 'supplier_sku', 'supplier_product_name', 'purchase_price', 'mrp', 'minimum_order_quantity', 'lead_time_days', 'tax_rate_id', 'is_preferred', 'is_active', 'last_purchase_price', 'last_purchased_at', 'product_performance_score', 'price_score', 'delivery_score', 'return_quality_score', 'service_score', 'overall_score'])]
class SupplierProduct extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'mrp' => 'decimal:2',
            'minimum_order_quantity' => 'decimal:3',
            'is_preferred' => 'boolean',
            'is_active' => 'boolean',
            'last_purchase_price' => 'decimal:2',
            'last_purchased_at' => 'datetime',
            'product_performance_score' => 'decimal:2',
            'price_score' => 'decimal:2',
            'delivery_score' => 'decimal:2',
            'return_quality_score' => 'decimal:2',
            'service_score' => 'decimal:2',
            'overall_score' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(InventoryTaxRate::class, 'tax_rate_id');
    }
}
