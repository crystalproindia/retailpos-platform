<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasBillingWebhookEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'encrypted',
            'normalized_payload' => 'array',
            'received_at' => 'datetime',
            'verified_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo { return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id'); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
