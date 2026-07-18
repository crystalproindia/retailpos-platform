<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['company_id', 'revisionable_type', 'revisionable_id', 'revision_number', 'action', 'snapshot', 'changed_fields', 'change_summary', 'created_by'])]
class CmsRevision extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'changed_fields' => 'array'];
    }

    public function revisionable(): MorphTo { return $this->morphTo(); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
