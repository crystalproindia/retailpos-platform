<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'branch_id', 'warehouse_id', 'product_id', 'minimum_stock', 'maximum_stock', 'reorder_point', 'reorder_quantity', 'safety_stock', 'supplier_lead_time_days', 'preferred_supplier_id', 'average_daily_sales', 'seasonal_factor', 'auto_generate_purchase_request', 'requires_approval', 'is_active'])]
class ReorderRule extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'minimum_stock' => 'decimal:3',
            'maximum_stock' => 'decimal:3',
            'reorder_point' => 'decimal:3',
            'reorder_quantity' => 'decimal:3',
            'safety_stock' => 'decimal:3',
            'average_daily_sales' => 'decimal:3',
            'seasonal_factor' => 'decimal:3',
            'auto_generate_purchase_request' => 'boolean',
            'requires_approval' => 'boolean',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
