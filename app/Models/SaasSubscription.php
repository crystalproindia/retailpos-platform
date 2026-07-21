<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasSubscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'feature_snapshot' => 'array',
            'limit_snapshot' => 'array',
            'trial_starts_at' => 'date',
            'trial_ends_at' => 'date',
            'starts_at' => 'date',
            'current_period_starts_at' => 'date',
            'current_period_ends_at' => 'date',
            'renewal_date' => 'date',
            'grace_period_ends_at' => 'date',
            'cancellation_effective_at' => 'date',
            'pending_change_at' => 'date',
            'cancelled_at' => 'datetime',
            'suspended_at' => 'datetime',
            'reactivated_at' => 'datetime',
            'auto_renew' => 'boolean',
            'price_snapshot' => 'decimal:2',
            'tax_snapshot' => 'decimal:3',
            'setup_fee_snapshot' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'pending_saas_plan_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SaasSubscriptionEvent::class);
    }
}
