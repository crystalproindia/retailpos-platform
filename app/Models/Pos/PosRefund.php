<?php

namespace App\Models\Pos;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'pos_return_id', 'original_payment_id', 'method', 'amount', 'external_reference', 'status', 'processed_by', 'processed_at', 'metadata'])]
class PosRefund extends Model
{
    protected function casts(): array { return ['amount' => 'decimal:2', 'processed_at' => 'datetime', 'metadata' => 'array']; }
    public function posReturn(): BelongsTo { return $this->belongsTo(PosReturn::class, 'pos_return_id'); }
    public function originalPayment(): BelongsTo { return $this->belongsTo(PosPayment::class, 'original_payment_id'); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
}
