<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'webhook_endpoint_id', 'domain_event_log_id', 'event_key', 'payload', 'signature', 'status', 'response_code', 'response_body', 'attempt_count', 'next_retry_at', 'sent_at', 'completed_at', 'failed_at'])]
class WebhookDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_retry_at' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function eventLog(): BelongsTo
    {
        return $this->belongsTo(DomainEventLog::class, 'domain_event_log_id');
    }
}
