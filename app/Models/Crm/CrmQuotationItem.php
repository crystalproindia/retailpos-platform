<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quotation_id', 'name', 'description', 'quantity', 'unit', 'unit_price', 'discount_amount', 'discount_type', 'discount_percentage', 'tax_rate', 'tax_amount', 'line_total', 'sort_order'])]
class CrmQuotationItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percentage' => 'decimal:3',
            'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(CrmQuotation::class, 'quotation_id');
    }
}
