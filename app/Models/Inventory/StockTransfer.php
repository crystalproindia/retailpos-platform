<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'source_branch_id', 'destination_branch_id', 'source_warehouse_id', 'destination_warehouse_id', 'transfer_number', 'status', 'notes', 'requested_by', 'dispatched_by', 'received_by', 'submitted_at', 'dispatched_at', 'received_at', 'cancelled_at', 'cancelled_by'])]
class StockTransfer extends Model
{
    public const DRAFT = 'draft';
    public const IN_TRANSIT = 'in_transit';
    public const RECEIVED = 'received';
    public const CANCELLED = 'cancelled';

    protected function casts(): array { return ['submitted_at' => 'datetime', 'dispatched_at' => 'datetime', 'received_at' => 'datetime', 'cancelled_at' => 'datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function sourceOutlet(): BelongsTo { return $this->belongsTo(Branch::class, 'source_branch_id'); }
    public function destinationOutlet(): BelongsTo { return $this->belongsTo(Branch::class, 'destination_branch_id'); }
    public function sourceWarehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'source_warehouse_id'); }
    public function destinationWarehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'destination_warehouse_id'); }
    public function items(): HasMany { return $this->hasMany(StockTransferItem::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
}
