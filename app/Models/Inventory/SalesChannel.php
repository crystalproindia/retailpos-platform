<?php

namespace App\Models\Inventory;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\Promotions\PromotionChannelTarget;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'code', 'type', 'description', 'is_online', 'is_active', 'sync_enabled', 'price_strategy', 'stock_strategy', 'sort_order'])]
class SalesChannel extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'is_active' => 'boolean',
            'sync_enabled' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ChannelProductMapping::class);
    }

    public function promotionTargets(): HasMany
    {
        return $this->hasMany(PromotionChannelTarget::class, 'sales_channel_id');
    }
}
