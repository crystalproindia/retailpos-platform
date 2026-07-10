<?php

namespace App\Models\Crm;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'slug', 'color', 'is_active'])]
class CrmTag extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tenantCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(CrmLead::class, 'crm_lead_tag');
    }

    public function crmCompanies(): BelongsToMany
    {
        return $this->belongsToMany(CrmCompany::class, 'crm_company_tag');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(CrmContact::class, 'crm_contact_tag');
    }
}
