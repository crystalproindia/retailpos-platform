<?php

namespace App\Models\Pos;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'register_id', 'branch_id', 'opened_by', 'opened_at', 'opening_cash', 'closed_by', 'closed_at', 'closing_cash', 'expected_cash', 'variance', 'status', 'notes'])]
class PosRegisterSession extends Model
{
    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime', 'opening_cash' => 'decimal:2', 'closing_cash' => 'decimal:2', 'expected_cash' => 'decimal:2', 'variance' => 'decimal:2'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function register(): BelongsTo { return $this->belongsTo(PosRegister::class, 'register_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
    public function sales(): HasMany { return $this->hasMany(PosSale::class, 'register_session_id'); }
}
