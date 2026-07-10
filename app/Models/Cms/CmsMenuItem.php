<?php

namespace App\Models\Cms;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['menu_id', 'parent_id', 'label', 'url', 'route_name', 'icon', 'opens_new_tab', 'is_enabled', 'sort_order'])]
class CmsMenuItem extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'opens_new_tab' => 'boolean',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(CmsMenu::class, 'menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
