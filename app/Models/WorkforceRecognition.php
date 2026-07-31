<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'employee_id', 'granted_by', 'type', 'title', 'message', 'recognized_on', 'revoked_at'])] class WorkforceRecognition extends Model
{
    protected function casts(): array
    {
        return ['recognized_on' => 'date', 'revoked_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(WorkforceEmployee::class, 'employee_id');
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
