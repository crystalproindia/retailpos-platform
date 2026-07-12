<?php

namespace App\Models\Cms;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'case_study_id', 'media_id', 'section_type', 'title', 'subtitle', 'content', 'settings', 'sort_order', 'is_active'])]
class CmsCaseStudySection extends Model
{
    use SoftDeletes;
    protected function casts(): array { return ['settings' => 'array', 'is_active' => 'boolean']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function caseStudy(): BelongsTo { return $this->belongsTo(CmsCaseStudy::class, 'case_study_id'); }
    public function media(): BelongsTo { return $this->belongsTo(CmsMedia::class, 'media_id'); }
}
