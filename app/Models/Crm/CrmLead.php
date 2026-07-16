<?php

namespace App\Models\Crm;

use App\Enums\Crm\LeadPriority;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'branch_id', 'crm_company_id', 'crm_contact_id', 'source_id', 'status_id', 'assigned_user_id', 'created_by', 'title', 'business_name', 'contact_name', 'email', 'phone', 'alternate_phone', 'industry', 'city', 'country', 'business_type', 'interested_modules', 'expected_value', 'expected_timeline', 'currency', 'priority', 'lead_score', 'next_follow_up_at', 'last_contacted_at', 'lost_reason', 'description', 'metadata', 'converted_at', 'won_at', 'lost_at'])]
class CrmLead extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'interested_modules' => 'array',
            'metadata' => 'array',
            'expected_value' => 'decimal:2',
            'priority' => LeadPriority::class,
            'next_follow_up_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'converted_at' => 'datetime',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    public function tenantCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function crmCompany(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CrmLeadSource::class, 'source_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CrmLeadStatus::class, 'status_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'crm_lead_id')->latest('scheduled_at');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(CrmNote::class, 'notable')->latest();
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CrmTag::class, 'crm_lead_tag');
    }

    public function demoSchedules(): HasMany
    {
        return $this->hasMany(DemoSchedule::class, 'lead_id')->latest('starts_at');
    }

    public function latestDemoSchedule(): HasOne
    {
        return $this->hasOne(DemoSchedule::class, 'lead_id')->latestOfMany('starts_at');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(CrmQuotation::class, 'lead_id')->latest('created_at');
    }

    public function proformas(): HasMany
    {
        return $this->hasMany(CrmProformaInvoice::class, 'lead_id')->latest('created_at');
    }

    public function latestQuotation(): HasOne
    {
        return $this->hasOne(CrmQuotation::class, 'lead_id')->latestOfMany('created_at');
    }

    public function latestProforma(): HasOne
    {
        return $this->hasOne(CrmProformaInvoice::class, 'lead_id')->latestOfMany('created_at');
    }

    public function latestActivity(): HasOne
    {
        return $this->hasOne(CrmActivity::class, 'crm_lead_id')->latestOfMany('scheduled_at');
    }

    public function crmCustomer(): HasOne
    {
        return $this->hasOne(CrmCustomer::class, 'lead_id');
    }

    public function isConverted(): bool
    {
        return $this->converted_at !== null;
    }
}
