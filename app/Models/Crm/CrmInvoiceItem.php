<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'name', 'description', 'quantity', 'unit', 'unit_price', 'discount_type', 'discount_value', 'discount_amount', 'tax_rate', 'tax_amount', 'line_subtotal', 'line_total', 'sort_order'])]
class CrmInvoiceItem extends Model
{
    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_value' => 'decimal:3', 'discount_amount' => 'decimal:2', 'tax_rate' => 'decimal:3', 'tax_amount' => 'decimal:2', 'line_subtotal' => 'decimal:2', 'line_total' => 'decimal:2']; }
    public function invoice(): BelongsTo { return $this->belongsTo(CrmInvoice::class, 'invoice_id'); }
}
