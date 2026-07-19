<?php

namespace App\Models\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'crm_lead_id', 'opportunity_id', 'crm_company_id', 'crm_contact_id', 'assigned_user_id', 'created_by', 'type', 'subject', 'description', 'scheduled_at', 'reminder_at', 'timezone', 'completed_at', 'completed_by', 'cancelled_at', 'cancelled_by', 'outcome', 'priority', 'follow_up_status'])]
class CrmActivity extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'priority' => LeadPriority::class,
            'scheduled_at' => 'datetime',
            'reminder_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    public function crmCompany(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
    public function cancelledBy(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->completed_at === null && $this->cancelled_at === null && $this->scheduled_at?->isPast();
    }
}
