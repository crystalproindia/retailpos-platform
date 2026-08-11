<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\StockCountStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'branch_id', 'warehouse_id', 'stock_location_id', 'count_number', 'type', 'status', 'assigned_to', 'due_date', 'notes', 'created_by', 'submitted_by', 'approved_by', 'posted_by', 'started_at', 'submitted_at', 'approved_at', 'posted_at'])]
class InventoryStockCount extends Model
{
    protected function casts(): array
    {
        return [
            'status' => StockCountStatus::class,
            'due_date' => 'date',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryStockCountItem::class);
    }
}
