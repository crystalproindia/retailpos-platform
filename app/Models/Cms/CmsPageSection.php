<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'page_id', 'section_key', 'section_type', 'title', 'subtitle', 'content', 'settings', 'sort_order', 'is_active'])]
class CmsPageSection extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'page_id');
    }
}
