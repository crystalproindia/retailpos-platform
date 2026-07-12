<?php

namespace App\Models\Promotions;

use App\Enums\Promotions\PromotionActionType;
use App\Models\Company;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'promotion_rule_id', 'action_type', 'discount_value', 'discount_percentage', 'fixed_price', 'buy_quantity', 'get_quantity', 'free_product_id', 'applies_to_same_product', 'maximum_free_quantity', 'maximum_discount_amount', 'sort_order'])]
class PromotionAction extends Model
{
    protected function casts(): array { return ['action_type' => PromotionActionType::class, 'discount_value' => 'decimal:2', 'discount_percentage' => 'decimal:3', 'fixed_price' => 'decimal:2', 'buy_quantity' => 'decimal:3', 'get_quantity' => 'decimal:3', 'maximum_free_quantity' => 'decimal:3', 'maximum_discount_amount' => 'decimal:2', 'applies_to_same_product' => 'boolean']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function rule(): BelongsTo { return $this->belongsTo(PromotionRule::class, 'promotion_rule_id'); }
    public function freeProduct(): BelongsTo { return $this->belongsTo(Product::class, 'free_product_id'); }
}
