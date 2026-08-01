<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'code', 'is_paid', 'annual_entitlement', 'accrual_method', 'carry_forward_allowed', 'maximum_carry_forward', 'negative_balance_allowed', 'attachment_required_after_days', 'approval_required', 'is_active', 'description'])]
class LeaveType extends Model
{
    use SoftDeletes;
    protected function casts(): array { return ['is_paid' => 'boolean', 'carry_forward_allowed' => 'boolean', 'negative_balance_allowed' => 'boolean', 'approval_required' => 'boolean', 'is_active' => 'boolean', 'annual_entitlement' => 'decimal:2', 'maximum_carry_forward' => 'decimal:2']; }
    public function balances(): HasMany { return $this->hasMany(EmployeeLeaveBalance::class); }
}
