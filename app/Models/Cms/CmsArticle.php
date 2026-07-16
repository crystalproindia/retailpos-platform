<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'cover_image_id', 'created_by', 'updated_by', 'title', 'slug', 'excerpt', 'content', 'author_name', 'category', 'tags', 'meta_title', 'meta_description', 'canonical_url', 'schema_json', 'status', 'published_at', 'include_in_sitemap', 'sitemap_priority', 'sitemap_changefreq'])]
class CmsArticle extends Model
{
    use Auditable, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return ['tags' => 'array', 'published_at' => 'datetime', 'include_in_sitemap' => 'boolean', 'sitemap_priority' => 'decimal:1'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function coverImage(): BelongsTo { return $this->belongsTo(CmsMedia::class, 'cover_image_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
