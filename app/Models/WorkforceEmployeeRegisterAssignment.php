<?php

namespace App\Models;

use App\Models\Pos\PosRegister;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'employee_id', 'register_id', 'is_active'])]
class WorkforceEmployeeRegisterAssignment extends Model
{
    protected $table = 'workforce_employee_register_assignments';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(WorkforceEmployee::class, 'employee_id');
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'register_id');
    }
}
