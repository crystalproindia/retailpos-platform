<?php

namespace App\Models\Cms;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['content_page_id', 'section_key', 'section_type', 'title', 'subtitle', 'eyebrow', 'body', 'image_url', 'primary_cta_label', 'primary_cta_url', 'secondary_cta_label', 'secondary_cta_url', 'items', 'settings', 'sort_order', 'is_enabled'])]
class CmsContentSection extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['items' => 'array', 'settings' => 'array', 'sort_order' => 'integer', 'is_enabled' => 'boolean'];
    }

    public function page(): BelongsTo { return $this->belongsTo(CmsContentPage::class, 'content_page_id'); }
}
