<?php

namespace App\Models\Pos;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'product_id', 'related_product_id', 'co_purchase_count', 'last_purchased_together_at'])]
class PosProductPairSummary extends Model
{
    protected function casts(): array { return ['last_purchased_together_at' => 'datetime']; }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function relatedProduct(): BelongsTo { return $this->belongsTo(Product::class, 'related_product_id'); }
}
