<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'block_key', 'title', 'content', 'links', 'sort_order', 'is_enabled'])]
class CmsFooterBlock extends Model
{
    use Auditable;

    protected function casts(): array { return ['links' => 'array', 'sort_order' => 'integer', 'is_enabled' => 'boolean']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
