<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'platform', 'url', 'icon', 'is_enabled', 'sort_order'])]
class CmsSocialLink extends Model
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
}
