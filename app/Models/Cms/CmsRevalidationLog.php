<?php

namespace App\Models\Cms;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'content_type', 'slug', 'path', 'status', 'response_code', 'message'])]
class CmsRevalidationLog extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED_NOT_CONFIGURED = 'skipped_not_configured';

    protected function casts(): array
    {
        return [
            'response_code' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
