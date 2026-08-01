<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'applicable_outlet_id', 'name', 'code', 'start_time', 'end_time', 'crosses_midnight', 'unpaid_break_minutes', 'grace_before_minutes', 'grace_after_minutes', 'minimum_work_minutes', 'standard_work_minutes', 'overtime_after_minutes', 'color_token', 'is_active', 'created_by'])]
class ShiftTemplate extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['crosses_midnight' => 'boolean', 'is_active' => 'boolean'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function applicableOutlet(): BelongsTo { return $this->belongsTo(Branch::class, 'applicable_outlet_id'); }
    public function assignments(): HasMany { return $this->hasMany(ShiftAssignment::class); }
}
