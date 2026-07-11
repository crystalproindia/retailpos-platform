<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'key', 'name', 'category', 'status', 'message', 'payload', 'checked_at'])]
class SystemHealthCheck extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
