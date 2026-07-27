<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasPublicSignupSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'verification_expires_at' => 'datetime',
            'resend_available_at' => 'datetime',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'consent_accepted_at' => 'datetime',
            'started_at' => 'datetime',
        ];
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(SaasTenantOnboarding::class, 'saas_tenant_onboarding_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
