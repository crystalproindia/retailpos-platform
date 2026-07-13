<?php

namespace App\Models\Pos;

use App\Models\Customers\Customer;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'customer_id', 'product_id', 'category_id', 'purchase_count', 'quantity_purchased', 'total_spent', 'first_purchased_at', 'last_purchased_at'])]
class CustomerProductSummary extends Model
{
    protected function casts(): array { return ['quantity_purchased' => 'decimal:3', 'total_spent' => 'decimal:2', 'first_purchased_at' => 'datetime', 'last_purchased_at' => 'datetime']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function category(): BelongsTo { return $this->belongsTo(InventoryCategory::class); }
}
