<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'domain_event_log_id', 'notification_id', 'event_key', 'channel', 'recipient', 'status', 'attempt_count', 'provider', 'provider_message_id', 'payload', 'response', 'failure_reason', 'queued_at', 'sent_at', 'delivered_at', 'failed_at', 'next_retry_at'])]
class NotificationDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventLog(): BelongsTo
    {
        return $this->belongsTo(DomainEventLog::class, 'domain_event_log_id');
    }
}
