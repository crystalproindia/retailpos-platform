<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'source_branch_id', 'destination_branch_id', 'source_warehouse_id', 'destination_warehouse_id', 'source_stock_location_id', 'destination_stock_location_id', 'transfer_number', 'idempotency_key', 'status', 'notes', 'requested_by', 'approved_by', 'packed_by', 'dispatched_by', 'received_by', 'submitted_at', 'requested_at', 'approved_at', 'packed_at', 'dispatched_at', 'expected_arrival_at', 'received_at', 'cancelled_at', 'cancelled_by', 'rejected_by', 'rejected_at', 'rejection_reason', 'cancellation_reason'])]
class StockTransfer extends Model
{
    public const DRAFT = 'draft';

    public const REQUESTED = 'requested';

    public const PENDING_APPROVAL = 'pending_approval';

    public const APPROVED = 'approved';

    public const PACKING = 'packing';

    public const DISPATCHED = 'dispatched';

    public const IN_TRANSIT = 'in_transit';

    public const PARTIALLY_RECEIVED = 'partially_received';

    public const RECEIVED = 'received';

    public const DISCREPANCY = 'discrepancy';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'packed_at' => 'datetime', 'dispatched_at' => 'datetime', 'expected_arrival_at' => 'datetime', 'received_at' => 'datetime', 'cancelled_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sourceOutlet(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destinationOutlet(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_stock_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'destination_stock_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(InventoryTransferReceipt::class);
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(InventoryTransferDiscrepancy::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function packer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by');
    }
}
