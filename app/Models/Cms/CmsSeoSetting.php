<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'default_og_image_id', 'default_twitter_image_id', 'default_meta_title', 'default_meta_description', 'default_meta_keywords', 'default_canonical_url', 'company_name', 'company_logo_url', 'contact_phone_india', 'contact_phone_singapore', 'contact_phone_malaysia', 'contact_email', 'address', 'same_as_social_links', 'schema_markup', 'default_schema_organization', 'robots_txt', 'robots_default_index', 'robots_default_follow', 'sitemap_enabled', 'sitemap_url', 'search_console_verification', 'google_analytics_id', 'google_tag_manager_id', 'facebook_pixel_id', 'linkedin_insight_tag', 'microsoft_clarity_id'])]
class CmsSeoSetting extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'sitemap_enabled' => 'boolean',
            'robots_default_index' => 'boolean',
            'robots_default_follow' => 'boolean',
            'same_as_social_links' => 'array',
            'default_schema_organization' => 'array',
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

    public function defaultTwitterImage(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'default_twitter_image_id');
    }
}
