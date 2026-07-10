<?php

namespace App\Models\Crm;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'user_id', 'notable_type', 'notable_id', 'body', 'is_pinned'])]
class CrmNote extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    public function tenantCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}
