<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quotation_id', 'channel', 'recipient', 'status', 'metadata', 'created_by', 'sent_at', 'failed_at'])]
class CrmQuotationShare extends Model
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(CrmQuotation::class, 'quotation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
