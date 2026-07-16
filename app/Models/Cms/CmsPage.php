<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'author_user_id', 'updated_by', 'featured_image_id', 'slug', 'route_path', 'title', 'h1', 'page_type', 'subtitle', 'hero_content', 'intro_content', 'body_content', 'footer_seo_content', 'cta_label', 'cta_url', 'primary_cta_label', 'primary_cta_url', 'secondary_cta_label', 'secondary_cta_url', 'content_sections', 'faq_items', 'related_product_keys', 'related_industry_keys', 'status', 'is_active', 'robots_index', 'robots_follow', 'schema_json', 'include_in_sitemap', 'sitemap_priority', 'sitemap_changefreq', 'sort_order', 'published_at', 'scheduled_for'])]
class CmsPage extends Model
{
    use Auditable, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'is_active' => 'boolean',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
            'include_in_sitemap' => 'boolean',
            'content_sections' => 'array',
            'faq_items' => 'array',
            'related_product_keys' => 'array',
            'related_industry_keys' => 'array',
            'sitemap_priority' => 'decimal:1',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'featured_image_id');
    }

    public function seo(): HasOne
    {
        return $this->hasOne(CmsPageSeo::class, 'page_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(CmsPageRevision::class, 'page_id')->latest('revision_number');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CmsPageSection::class, 'page_id')->orderBy('sort_order');
    }
}
