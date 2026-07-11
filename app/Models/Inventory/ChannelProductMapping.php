<?php

namespace App\Models\Inventory;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'sales_channel_id', 'product_id', 'channel_sku', 'channel_product_name', 'channel_price', 'channel_mrp', 'channel_offer_price', 'stock_buffer_quantity', 'max_listed_quantity', 'sync_product', 'sync_price', 'sync_stock', 'last_synced_at', 'sync_status', 'sync_error'])]
class ChannelProductMapping extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'channel_price' => 'decimal:2',
            'channel_mrp' => 'decimal:2',
            'channel_offer_price' => 'decimal:2',
            'stock_buffer_quantity' => 'decimal:3',
            'max_listed_quantity' => 'decimal:3',
            'sync_product' => 'boolean',
            'sync_price' => 'boolean',
            'sync_stock' => 'boolean',
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
}
