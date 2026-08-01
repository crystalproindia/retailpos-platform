<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'employee_id', 'leave_type_id', 'period', 'opening_balance', 'accrued', 'used', 'pending', 'adjusted', 'remaining'])]
class EmployeeLeaveBalance extends Model
{
    protected function casts(): array { return ['opening_balance' => 'decimal:2', 'accrued' => 'decimal:2', 'used' => 'decimal:2', 'pending' => 'decimal:2', 'adjusted' => 'decimal:2', 'remaining' => 'decimal:2']; }
    public function employee(): BelongsTo { return $this->belongsTo(WorkforceEmployee::class, 'employee_id'); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
    public function transactions(): HasMany { return $this->hasMany(LeaveBalanceTransaction::class); }
}
