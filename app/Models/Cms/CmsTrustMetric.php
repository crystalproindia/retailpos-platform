<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'label', 'value', 'description', 'icon', 'show_on_homepage', 'is_active', 'sort_order'])]
class CmsTrustMetric extends Model
{
    use Auditable, SoftDeletes;
    protected function casts(): array { return ['show_on_homepage' => 'boolean', 'is_active' => 'boolean']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
