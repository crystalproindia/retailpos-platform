<?php

namespace App\Models\Crm;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'lead_id', 'crm_company_id', 'crm_contact_id', 'title', 'description', 'stage', 'expected_value', 'currency', 'probability_percentage', 'expected_close_date', 'assigned_user_id', 'won_at', 'lost_at', 'loss_reason', 'created_by', 'updated_by'])]
class CrmOpportunity extends Model
{
    protected function casts(): array
    {
        return [
            'expected_value' => 'decimal:2',
            'probability_percentage' => 'integer',
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function lead(): BelongsTo { return $this->belongsTo(CrmLead::class, 'lead_id'); }
    public function crmCompany(): BelongsTo { return $this->belongsTo(CrmCompany::class, 'crm_company_id'); }
    public function contact(): BelongsTo { return $this->belongsTo(CrmContact::class, 'crm_contact_id'); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function quotations(): HasMany { return $this->hasMany(CrmQuotation::class, 'opportunity_id'); }
    public function activities(): HasMany { return $this->hasMany(CrmActivity::class, 'opportunity_id'); }
    public function stageHistory(): HasMany { return $this->hasMany(CrmOpportunityStageHistory::class, 'opportunity_id')->latest('changed_at'); }

    public function weightedValue(): float
    {
        return round((float) $this->expected_value * ((int) $this->probability_percentage / 100), 2);
    }
}
