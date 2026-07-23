<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasBillingCheckoutSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(SaasSubscriptionInvoice::class, 'saas_subscription_invoice_id'); }
    public function subscription(): BelongsTo { return $this->belongsTo(SaasSubscription::class, 'saas_subscription_id'); }
    public function integration(): BelongsTo { return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id'); }
}
