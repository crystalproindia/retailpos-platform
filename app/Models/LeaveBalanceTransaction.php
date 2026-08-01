<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'employee_leave_balance_id', 'leave_request_id', 'entry_type', 'amount', 'reason', 'actor_user_id'])]
class LeaveBalanceTransaction extends Model
{
    protected function casts(): array { return ['amount' => 'decimal:2']; }
    public function balance(): BelongsTo { return $this->belongsTo(EmployeeLeaveBalance::class, 'employee_leave_balance_id'); }
    public function leaveRequest(): BelongsTo { return $this->belongsTo(LeaveRequest::class); }
}
