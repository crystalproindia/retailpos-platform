<?php

namespace App\Models\Crm;

use App\Enums\Crm\PreferredContactMethod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'branch_id', 'crm_company_id', 'assigned_user_id', 'first_name', 'last_name', 'job_title', 'email', 'phone', 'alternate_phone', 'preferred_contact_method', 'is_primary', 'notes'])]
class CrmContact extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'preferred_contact_method' => PreferredContactMethod::class,
            'is_primary' => 'boolean',
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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'crm_contact_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'crm_contact_id');
    }

    public function timelineNotes(): MorphMany
    {
        return $this->morphMany(CrmNote::class, 'notable')->latest();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CrmTag::class, 'crm_contact_tag');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
