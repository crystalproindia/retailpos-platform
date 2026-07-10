<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'media_id', 'key', 'name', 'heading', 'subheading', 'content', 'cta_label', 'cta_url', 'is_enabled', 'sort_order'])]
class CmsHomepageSection extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'media_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CmsHomepageSectionItem::class, 'section_id')->orderBy('sort_order');
    }
}
