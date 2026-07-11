<?php

namespace App\Models\Inventory;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'short_code', 'type', 'decimal_allowed', 'conversion_factor', 'base_unit_id', 'is_system', 'is_active'])]
class InventoryUnit extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'decimal_allowed' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'conversion_factor' => 'decimal:6',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'base_unit_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_id');
    }
}
