<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'source_url', 'broken_url', 'status_code', 'last_checked_at', 'is_resolved'])]
class CmsBrokenLink extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'last_checked_at' => 'datetime',
            'is_resolved' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
