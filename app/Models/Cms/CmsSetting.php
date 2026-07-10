<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'media_id', 'key', 'label', 'value', 'value_type'])]
class CmsSetting extends Model
{
    use Auditable;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'media_id');
    }
}
