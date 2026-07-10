<?php

namespace App\Models\Cms;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['page_id', 'meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_title', 'og_description', 'og_image_id', 'og_type', 'twitter_title', 'twitter_description', 'twitter_image_id', 'twitter_card'])]
class CmsPageSeo extends Model
{
    use Auditable;

    protected $table = 'cms_page_seo';

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'page_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'og_image_id');
    }

    public function twitterImage(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'twitter_image_id');
    }
}
