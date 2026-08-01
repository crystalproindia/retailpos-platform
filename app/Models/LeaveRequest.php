<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'employee_id', 'leave_type_id', 'outlet_id', 'starts_on', 'ends_on', 'day_portion', 'requested_days', 'reason', 'attachment_path', 'status', 'requested_by', 'reviewed_by', 'review_note', 'reviewed_at', 'withdrawn_at'])]
class LeaveRequest extends Model
{
    protected function casts(): array { return ['starts_on' => 'date', 'ends_on' => 'date', 'requested_days' => 'decimal:2', 'reviewed_at' => 'datetime', 'withdrawn_at' => 'datetime']; }
    public function employee(): BelongsTo { return $this->belongsTo(WorkforceEmployee::class, 'employee_id'); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Branch::class, 'outlet_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function balanceTransactions(): HasMany { return $this->hasMany(LeaveBalanceTransaction::class); }
}
