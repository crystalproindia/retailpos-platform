<?php

namespace App\Models\Crm;

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

#[Fillable(['company_id', 'branch_id', 'assigned_user_id', 'name', 'legal_name', 'website', 'industry', 'email', 'phone', 'city', 'state', 'country', 'address', 'estimated_value', 'is_active'])]
class CrmCompany extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'is_active' => 'boolean',
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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'crm_company_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'crm_company_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'crm_company_id');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(CrmNote::class, 'notable')->latest();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CrmTag::class, 'crm_company_tag');
    }

    public function primaryContact(): ?CrmContact
    {
        return $this->contacts->firstWhere('is_primary', true) ?? $this->contacts->first();
    }
}
