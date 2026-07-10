<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['page_id', 'user_id', 'revision_number', 'title', 'subtitle', 'hero_content', 'body_content', 'status'])]
class CmsPageRevision extends Model
{
    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'page_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
