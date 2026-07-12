<?php

namespace App\Models\Promotions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'title', 'cart_payload', 'result_payload', 'total_before_discount', 'total_discount', 'total_after_discount', 'simulated_at'])]
class PromotionSimulation extends Model
{
    protected function casts(): array { return ['cart_payload' => 'array', 'result_payload' => 'array', 'total_before_discount' => 'decimal:2', 'total_discount' => 'decimal:2', 'total_after_discount' => 'decimal:2', 'simulated_at' => 'datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
