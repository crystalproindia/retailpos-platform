<?php

namespace App\Models\Crm;

use App\Enums\Crm\LeadStageType;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'slug', 'stage_type', 'color', 'tone', 'probability', 'is_won', 'is_lost', 'is_active', 'sort_order'])]
class CrmLeadStatus extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'stage_type' => LeadStageType::class,
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tenantCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'status_id');
    }
}
