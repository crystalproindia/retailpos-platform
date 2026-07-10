<?php

namespace App\Models\Cms;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['section_id', 'media_id', 'title', 'description', 'icon', 'link_label', 'link_url', 'is_enabled', 'sort_order'])]
class CmsHomepageSectionItem extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CmsHomepageSection::class, 'section_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'media_id');
    }
}
