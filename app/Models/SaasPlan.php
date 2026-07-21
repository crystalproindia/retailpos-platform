<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaasPlan extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'tax_percentage' => 'decimal:3',
            'is_public' => 'boolean',
            'is_recommended' => 'boolean',
            'is_custom' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(SaasPlanFeature::class);
    }

    public function limits(): HasMany
    {
        return $this->hasMany(SaasPlanLimit::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SaasPlanVersion::class);
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'plan_id' => $this->id,
            'plan_code' => $this->code,
            'name' => $this->name,
            'price' => $this->base_price,
            'setup_fee' => $this->setup_fee,
            'tax_percentage' => $this->tax_percentage,
            'billing_interval' => $this->billing_interval,
            'features' => $this->features
                ->where('is_enabled', true)
                ->pluck('is_enabled', 'feature_key')
                ->all(),
            'limits' => $this->limits
                ->mapWithKeys(fn (SaasPlanLimit $limit) => [$limit->limit_key => $limit->limit_value])
                ->all(),
        ];
    }
}
