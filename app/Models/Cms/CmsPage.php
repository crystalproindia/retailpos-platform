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

#[Fillable(['company_id', 'author_user_id', 'featured_image_id', 'slug', 'title', 'page_type', 'subtitle', 'hero_content', 'body_content', 'cta_label', 'cta_url', 'status', 'is_active', 'sort_order', 'published_at', 'scheduled_for'])]
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
