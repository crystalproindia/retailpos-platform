<?php

namespace App\Models\Crm;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'branch_id', 'lead_id', 'from_status_id', 'to_status_id', 'from_stage', 'to_stage', 'event_type', 'idempotency_key', 'priority_snapshot', 'assigned_user_id', 'assigned_user_name_snapshot', 'lost_reason_snapshot', 'lost_note_snapshot', 'actor_id', 'actor_name_snapshot', 'occurred_at', 'metadata'])]
class CrmLeadStageHistory extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(CrmLeadStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(CrmLeadStatus::class, 'to_status_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
