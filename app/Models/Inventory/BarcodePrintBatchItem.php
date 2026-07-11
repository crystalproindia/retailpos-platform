<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['print_batch_id', 'product_id', 'quantity', 'price_override', 'label_data'])]
class BarcodePrintBatchItem extends Model
{
    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:2',
            'label_data' => 'array',
        ];
    }

    public function printBatch(): BelongsTo
    {
        return $this->belongsTo(BarcodePrintBatch::class, 'print_batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
