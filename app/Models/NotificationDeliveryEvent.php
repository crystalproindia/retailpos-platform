<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'notification_delivery_id', 'provider', 'provider_event_id', 'event_type', 'from_status', 'to_status', 'metadata', 'occurred_at', 'processed_at'])]
class NotificationDeliveryEvent extends Model
{
    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function delivery(): BelongsTo { return $this->belongsTo(NotificationDelivery::class, 'notification_delivery_id'); }
}
