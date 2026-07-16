<?php

namespace App\Models\Crm;

use App\Enums\Crm\DemoMeetingMode;
use App\Enums\Crm\DemoScheduleStatus;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'lead_id', 'assigned_to', 'scheduled_by', 'title', 'scheduled_date', 'starts_at', 'ends_at', 'timezone', 'meeting_mode', 'meeting_link', 'customer_email', 'customer_phone', 'notes', 'status', 'completed_at', 'cancelled_at', 'external_calendar_provider', 'external_calendar_event_id', 'external_calendar_event_url', 'external_meeting_link', 'calendar_sync_status', 'calendar_synced_at'])]
class DemoSchedule extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'meeting_mode' => DemoMeetingMode::class,
            'status' => DemoScheduleStatus::class,
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'calendar_synced_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [DemoScheduleStatus::Scheduled, DemoScheduleStatus::Rescheduled], true);
    }
}
