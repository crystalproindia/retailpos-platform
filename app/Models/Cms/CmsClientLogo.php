<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'logo_media_id', 'name', 'website_url', 'industry', 'location', 'short_description', 'display_style', 'is_featured', 'show_on_homepage', 'show_on_case_studies', 'is_active', 'sort_order'])]
class CmsClientLogo extends Model
{
    use Auditable, SoftDeletes;
    protected function casts(): array { return ['is_featured' => 'boolean', 'show_on_homepage' => 'boolean', 'show_on_case_studies' => 'boolean', 'is_active' => 'boolean']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function logoMedia(): BelongsTo { return $this->belongsTo(CmsMedia::class, 'logo_media_id'); }
}
