<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['company_id', 'employee_id', 'user_id', 'outlet_id', 'shift_assignment_id', 'attendance_date', 'scheduled_start_at', 'scheduled_end_at', 'checked_in_at', 'checked_out_at', 'total_break_minutes', 'worked_minutes', 'overtime_minutes', 'late_minutes', 'early_departure_minutes', 'attendance_status', 'attendance_source', 'check_in_method', 'check_out_method', 'attendance_state', 'is_manual', 'manually_entered_by', 'correction_status', 'notes', 'metadata'])]
class AttendanceRecord extends Model
{
    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'scheduled_start_at' => 'datetime', 'scheduled_end_at' => 'datetime', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'is_manual' => 'boolean', 'metadata' => 'array'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function employee(): BelongsTo { return $this->belongsTo(WorkforceEmployee::class, 'employee_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Branch::class, 'outlet_id'); }
    public function shiftAssignment(): BelongsTo { return $this->belongsTo(ShiftAssignment::class); }
    public function breaks(): HasMany { return $this->hasMany(AttendanceBreak::class, 'attendance_id'); }
    public function corrections(): HasMany { return $this->hasMany(AttendanceCorrection::class, 'attendance_id'); }
    public function overtimeReview(): HasOne { return $this->hasOne(OvertimeReview::class, 'attendance_id'); }
}
