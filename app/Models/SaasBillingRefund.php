<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasBillingRefund extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'amount' => 'decimal:2', 'approved_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function payment(): BelongsTo { return $this->belongsTo(SaasBillingPayment::class, 'saas_billing_payment_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(SaasSubscriptionInvoice::class, 'saas_subscription_invoice_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
