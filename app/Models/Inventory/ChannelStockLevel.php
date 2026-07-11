<?php

namespace App\Models\Inventory;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'sales_channel_id', 'product_id', 'warehouse_id', 'listed_quantity', 'reserved_quantity', 'available_quantity', 'buffer_quantity', 'last_synced_at', 'sync_status'])]
class ChannelStockLevel extends Model
{
    protected function casts(): array
    {
        return [
            'listed_quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'available_quantity' => 'decimal:3',
            'buffer_quantity' => 'decimal:3',
            'last_synced_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
