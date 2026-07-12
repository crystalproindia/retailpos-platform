<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'logo_or_photo_media_id', 'case_study_id', 'client_name', 'company_name', 'designation', 'testimonial_text', 'rating', 'industry', 'is_featured', 'show_on_homepage', 'is_active', 'sort_order'])]
class CmsTestimonial extends Model
{
    use Auditable, SoftDeletes;
    protected function casts(): array { return ['is_featured' => 'boolean', 'show_on_homepage' => 'boolean', 'is_active' => 'boolean']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function media(): BelongsTo { return $this->belongsTo(CmsMedia::class, 'logo_or_photo_media_id'); }
    public function caseStudy(): BelongsTo { return $this->belongsTo(CmsCaseStudy::class, 'case_study_id'); }
}
