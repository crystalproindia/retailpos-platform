<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'source_url', 'target_url', 'status_code', 'is_enabled', 'notes', 'hit_count', 'last_hit_at'])]
class CmsRedirect extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_enabled' => 'boolean',
            'hit_count' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
