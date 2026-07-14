<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'media_id', 'group', 'key', 'label', 'value', 'value_type', 'is_public'])]
class CmsSetting extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
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
}
