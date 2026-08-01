<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'attendance_id', 'employee_id', 'started_at', 'ended_at', 'duration_minutes', 'break_type', 'created_by', 'ended_by', 'notes'])]
class AttendanceBreak extends Model
{
    protected function casts(): array { return ['started_at' => 'datetime', 'ended_at' => 'datetime']; }
    public function attendance(): BelongsTo { return $this->belongsTo(AttendanceRecord::class, 'attendance_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(WorkforceEmployee::class, 'employee_id'); }
}
