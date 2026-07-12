<?php

namespace App\Models\Purchases;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'supplier_id', 'product_performance_score', 'price_score', 'delivery_score', 'return_quality_score', 'service_score', 'overall_score', 'purchase_value', 'received_quantity', 'rejected_quantity', 'returned_quantity', 'delayed_delivery_count', 'calculated_at', 'notes'])]
class SupplierScoreSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'product_performance_score' => 'decimal:2',
            'price_score' => 'decimal:2',
            'delivery_score' => 'decimal:2',
            'return_quality_score' => 'decimal:2',
            'service_score' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'purchase_value' => 'decimal:2',
            'received_quantity' => 'decimal:3',
            'rejected_quantity' => 'decimal:3',
            'returned_quantity' => 'decimal:3',
            'calculated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
