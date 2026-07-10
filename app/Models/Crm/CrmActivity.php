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

#[Fillable(['company_id', 'crm_lead_id', 'crm_company_id', 'crm_contact_id', 'assigned_user_id', 'created_by', 'type', 'subject', 'description', 'scheduled_at', 'completed_at', 'outcome', 'priority'])]
class CrmActivity extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'priority' => LeadPriority::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
