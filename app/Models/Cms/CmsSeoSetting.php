<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'default_og_image_id', 'default_meta_title', 'default_meta_description', 'default_meta_keywords', 'default_canonical_url', 'schema_markup', 'robots_txt', 'sitemap_enabled', 'search_console_verification', 'google_analytics_id', 'google_tag_manager_id', 'facebook_pixel_id', 'linkedin_insight_tag', 'microsoft_clarity_id'])]
class CmsSeoSetting extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'sitemap_enabled' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultOgImage(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'default_og_image_id');
    }
}
