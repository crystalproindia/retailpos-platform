<?php

namespace App\Models\Pos;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'branch_id', 'warehouse_id', 'stock_location_id', 'code', 'name', 'receipt_prefix', 'current_session_id', 'is_active', 'created_by'])]
class PosRegister extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currentSession(): BelongsTo
    {
        return $this->belongsTo(PosRegisterSession::class, 'current_session_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PosRegisterSession::class, 'register_id')->latest('opened_at');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class, 'register_id');
    }
}
