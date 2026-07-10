<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'folder_id', 'uploaded_by_user_id', 'name', 'file_name', 'disk', 'path', 'mime_type', 'extension', 'type', 'size', 'alt_text', 'is_optimized'])]
class CmsMedia extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'cms_media';

    protected function casts(): array
    {
        return [
            'is_optimized' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(CmsMediaFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
