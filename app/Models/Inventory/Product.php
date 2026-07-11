<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'branch_id', 'category_id', 'brand_id', 'unit_id', 'tax_rate_id', 'parent_product_id', 'type', 'name', 'slug', 'sku', 'barcode', 'hsn_code', 'description', 'short_description', 'cost_price', 'selling_price', 'mrp', 'wholesale_price', 'online_price', 'purchase_price', 'track_inventory', 'allow_negative_stock', 'has_variants', 'is_variant', 'variant_name', 'image', 'status', 'is_active'])]
class Product extends Model
{
    use Auditable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'mrp' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'online_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'track_inventory' => 'boolean',
            'allow_negative_stock' => 'boolean',
            'has_variants' => 'boolean',
            'is_variant' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(InventoryBrand::class, 'brand_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'unit_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(InventoryTaxRate::class, 'tax_rate_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_product_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_product_id')->orderBy('name');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('occurred_at');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttributeValue::class, 'product_variant_attributes', 'product_id', 'attribute_value_id')
            ->withPivot('attribute_id')
            ->withTimestamps();
    }

    public function reorderRules(): HasMany
    {
        return $this->hasMany(ReorderRule::class);
    }

    public function availableStock(): string
    {
        return (string) $this->stockLevels->sum('quantity_available');
    }
}
