<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'primary_branch_id', 'reporting_manager_id', 'employee_number',
    'first_name', 'last_name', 'display_name', 'work_email', 'work_mobile',
    'job_title', 'department', 'joining_date', 'status', 'manager_notes',
])]
class WorkforceEmployee extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['joining_date' => 'date'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'primary_branch_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporting_manager_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'workforce_employee_id');
    }

    public function outletAssignments(): HasMany
    {
        return $this->hasMany(WorkforceEmployeeOutletAssignment::class, 'employee_id');
    }

    public function warehouseAssignments(): HasMany
    {
        return $this->hasMany(WorkforceEmployeeWarehouseAssignment::class, 'employee_id');
    }

    public function registerAssignments(): HasMany
    {
        return $this->hasMany(WorkforceEmployeeRegisterAssignment::class, 'employee_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkforceInvitation::class, 'employee_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(WorkforceManagerReview::class, 'employee_id');
    }

    public function recognitions(): HasMany
    {
        return $this->hasMany(WorkforceRecognition::class, 'employee_id');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'employee_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class, 'employee_id');
    }
}
