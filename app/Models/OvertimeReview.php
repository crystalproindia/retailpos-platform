<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'attendance_id', 'employee_id', 'candidate_minutes', 'approved_minutes', 'status', 'reviewed_by', 'reason', 'reviewed_at'])]
class OvertimeReview extends Model
{
    protected function casts(): array { return ['reviewed_at' => 'datetime']; }
    public function attendance(): BelongsTo { return $this->belongsTo(AttendanceRecord::class, 'attendance_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(WorkforceEmployee::class, 'employee_id'); }
}
