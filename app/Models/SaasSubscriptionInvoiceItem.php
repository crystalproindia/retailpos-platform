<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasSubscriptionInvoiceItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2', 'credit_amount' => 'decimal:2', 'taxable_value' => 'decimal:2',
            'tax_rate' => 'decimal:3', 'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2', 'cess_amount' => 'decimal:2', 'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo { return $this->belongsTo(SaasSubscriptionInvoice::class, 'saas_subscription_invoice_id'); }
}
