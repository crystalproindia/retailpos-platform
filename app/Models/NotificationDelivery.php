<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'user_id', 'created_by', 'domain_event_log_id', 'notification_id', 'related_type', 'related_id', 'event_key', 'template_key', 'reminder_stage', 'reminder_source', 'channel', 'recipient', 'recipient_name', 'subject', 'status', 'attempt_count', 'provider', 'provider_message_id', 'idempotency_key', 'payload', 'sensitive_payload', 'response', 'failure_reason', 'queued_at', 'sent_at', 'delivered_at', 'failed_at', 'next_retry_at'])]
class NotificationDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sensitive_payload' => 'encrypted:array',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function eventLog(): BelongsTo
    {
        return $this->belongsTo(DomainEventLog::class, 'domain_event_log_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(NotificationDeliveryEvent::class)->latest('occurred_at');
    }

    public function maskedProviderMessageId(): ?string
    {
        if (! $this->provider_message_id) return null;

        return str($this->provider_message_id)->mask('*', 4, -4)->toString();
    }
}
