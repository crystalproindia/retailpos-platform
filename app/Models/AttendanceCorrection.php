<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'attendance_id', 'employee_id', 'requested_by', 'original_values', 'requested_values', 'reason', 'status', 'reviewed_by', 'review_note', 'reviewed_at'])]
class AttendanceCorrection extends Model
{
    protected function casts(): array { return ['original_values' => 'array', 'requested_values' => 'array', 'reviewed_at' => 'datetime']; }
    public function attendance(): BelongsTo { return $this->belongsTo(AttendanceRecord::class, 'attendance_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(WorkforceEmployee::class, 'employee_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
