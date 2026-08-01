<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'outlet_id', 'employee_id', 'weekday', 'is_active', 'notes', 'created_by'])]
class WeeklyOff extends Model
{
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function outlet(): BelongsTo { return $this->belongsTo(Branch::class, 'outlet_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(WorkforceEmployee::class, 'employee_id'); }
}
