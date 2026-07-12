<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'title', 'description', 'button_text', 'button_link', 'secondary_button_text', 'secondary_button_link', 'location', 'style', 'is_active', 'sort_order'])]
class CmsCtaBlock extends Model
{
    use Auditable, SoftDeletes;
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
