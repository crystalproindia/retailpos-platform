<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'question', 'answer', 'category', 'page_location', 'sort_order', 'is_active'])]
class CmsFaq extends Model
{
    use Auditable, SoftDeletes;
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
