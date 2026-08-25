<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'branch_id', 'condition_type', 'subject_type', 'subject_id', 'stage',
    'severity', 'is_active', 'cycle', 'context', 'first_detected_at', 'last_detected_at',
    'last_notified_at', 'recovered_at',
])]
class NotificationConditionState extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'context' => 'array',
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
