<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'event_key', 'database_enabled', 'email_enabled', 'whatsapp_enabled', 'sms_enabled', 'push_enabled', 'webhook_enabled', 'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end', 'timezone'])]
class NotificationPreference extends Model
{
    protected function casts(): array
    {
        return [
            'database_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'webhook_enabled' => 'boolean',
            'quiet_hours_enabled' => 'boolean',
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
}
