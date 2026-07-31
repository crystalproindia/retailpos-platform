<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'employee_id', 'reviewer_user_id', 'period_starts_at', 'period_ends_at', 'cycle', 'status', 'customer_service', 'product_knowledge', 'teamwork', 'reliability', 'communication', 'initiative', 'comments', 'employee_comment', 'submitted_at', 'acknowledged_at', 'finalized_at'])] class WorkforceManagerReview extends Model
{
    protected function casts(): array
    {
        return ['period_starts_at' => 'date', 'period_ends_at' => 'date', 'submitted_at' => 'datetime', 'acknowledged_at' => 'datetime', 'finalized_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(WorkforceEmployee::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
