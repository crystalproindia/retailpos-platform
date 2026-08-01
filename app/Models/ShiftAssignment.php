<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'employee_id', 'outlet_id', 'shift_template_id', 'work_date', 'assigned_by', 'assignment_source', 'status', 'notes'])]
class ShiftAssignment extends Model
{
    use SoftDeletes;

    protected function casts(): array { return ['work_date' => 'date']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function employee(): BelongsTo { return $this->belongsTo(WorkforceEmployee::class, 'employee_id'); }
    public function outlet(): BelongsTo { return $this->belongsTo(Branch::class, 'outlet_id'); }
    public function shift(): BelongsTo { return $this->belongsTo(ShiftTemplate::class, 'shift_template_id'); }
    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
