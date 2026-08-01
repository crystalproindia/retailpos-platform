<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'outlet_id', 'name', 'holiday_date', 'holiday_type', 'is_active', 'notes', 'created_by'])]
class Holiday extends Model
{
    protected function casts(): array { return ['holiday_date' => 'date', 'is_active' => 'boolean']; }
    public function outlet(): BelongsTo { return $this->belongsTo(Branch::class, 'outlet_id'); }
}
