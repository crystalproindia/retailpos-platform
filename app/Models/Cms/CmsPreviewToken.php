<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['company_id', 'previewable_type', 'previewable_id', 'token_hash', 'expires_at', 'created_by', 'revoked_at', 'last_used_at'])]
class CmsPreviewToken extends Model
{
    protected function casts(): array { return ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_used_at' => 'datetime']; }
    public function previewable(): MorphTo { return $this->morphTo(); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
