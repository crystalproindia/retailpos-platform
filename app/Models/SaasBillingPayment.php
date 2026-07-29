<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasBillingPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'paid_at' => 'datetime', 'reversed_at' => 'datetime', 'amount' => 'decimal:2', 'refund_total' => 'decimal:2'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(SaasSubscriptionInvoice::class, 'saas_subscription_invoice_id'); }
    public function subscription(): BelongsTo { return $this->belongsTo(SaasSubscription::class, 'saas_subscription_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function refunds(): HasMany { return $this->hasMany(SaasBillingRefund::class); }
}
