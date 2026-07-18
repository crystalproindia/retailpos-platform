<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'client_logo_media_id', 'featured_image_media_id', 'og_image_media_id', 'title', 'slug', 'client_name', 'industry', 'location', 'project_type', 'short_summary', 'challenge', 'solution', 'key_features', 'results', 'metrics', 'testimonial_quote', 'gallery_media_ids', 'related_product', 'related_module', 'related_industry', 'cta_text', 'cta_link', 'status', 'is_featured', 'sort_order', 'seo_title', 'seo_description', 'schema_json', 'published_at'])]
class CmsCaseStudy extends Model
{
    use Auditable, SoftDeletes;
    protected function casts(): array { return ['metrics' => 'array', 'gallery_media_ids' => 'array', 'schema_json' => 'array', 'is_featured' => 'boolean', 'published_at' => 'datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function clientLogoMedia(): BelongsTo { return $this->belongsTo(CmsMedia::class, 'client_logo_media_id'); }
    public function featuredImageMedia(): BelongsTo { return $this->belongsTo(CmsMedia::class, 'featured_image_media_id'); }
    public function ogImageMedia(): BelongsTo { return $this->belongsTo(CmsMedia::class, 'og_image_media_id'); }
    public function sections(): HasMany { return $this->hasMany(CmsCaseStudySection::class, 'case_study_id')->orderBy('sort_order'); }
    public function testimonials(): HasMany { return $this->hasMany(CmsTestimonial::class, 'case_study_id'); }
}
