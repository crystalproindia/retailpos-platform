<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'location', 'is_enabled'])]
class CmsMenu extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CmsMenuItem::class, 'menu_id')->orderBy('sort_order');
    }
}
