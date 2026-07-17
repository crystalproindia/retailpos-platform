<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'label', 'url', 'parent_id', 'sort_order', 'location', 'is_enabled', 'opens_new_tab'])]
class CmsNavigationItem extends Model
{
    use Auditable;

    protected function casts(): array { return ['is_enabled' => 'boolean', 'opens_new_tab' => 'boolean', 'sort_order' => 'integer']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order'); }
}
