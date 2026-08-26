<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'branch_id', 'payment_type', 'payment_id', 'status', 'note', 'reconciled_by', 'reconciled_at'])]
class FinanceReconciliation extends Model
{
    protected function casts(): array
    {
        return ['reconciled_at' => 'datetime'];
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
