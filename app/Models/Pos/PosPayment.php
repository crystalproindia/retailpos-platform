<?php

namespace App\Models\Pos;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'pos_sale_id', 'payment_method', 'amount', 'reference', 'status', 'paid_at', 'created_by', 'reversed_by', 'reversed_at'])]
class PosPayment extends Model
{
    protected function casts(): array { return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'reversed_at' => 'datetime']; }
    public function sale(): BelongsTo { return $this->belongsTo(PosSale::class, 'pos_sale_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reverser(): BelongsTo { return $this->belongsTo(User::class, 'reversed_by'); }
}
