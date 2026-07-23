<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasSubscriptionInvoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'billing_period_starts_at' => 'date',
            'billing_period_ends_at' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'plan_snapshot' => 'array',
            'reverse_charge' => 'boolean',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'adjustment_total' => 'decimal:2',
            'credit_total' => 'decimal:2',
            'taxable_total' => 'decimal:2',
            'cgst_total' => 'decimal:2',
            'sgst_total' => 'decimal:2',
            'igst_total' => 'decimal:2',
            'cess_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_refunded' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(SaasSubscription::class, 'saas_subscription_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(SaasPlan::class, 'saas_plan_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function items(): HasMany { return $this->hasMany(SaasSubscriptionInvoiceItem::class)->orderBy('sort_order'); }
    public function payments(): HasMany { return $this->hasMany(SaasBillingPayment::class)->latest('paid_at'); }
    public function refunds(): HasMany { return $this->hasMany(SaasBillingRefund::class); }

    public function isPayable(): bool
    {
        return in_array($this->status, ['issued', 'partially_paid', 'overdue'], true) && $this->balance_due > 0;
    }
}
