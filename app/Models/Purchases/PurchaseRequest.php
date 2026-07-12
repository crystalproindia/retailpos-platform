<?php

namespace App\Models\Purchases;

use App\Enums\Purchases\PurchaseRequestPriority;
use App\Enums\Purchases\PurchaseRequestStatus;
use App\Enums\Purchases\PurchaseSourceType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'branch_id', 'warehouse_id', 'request_number', 'source_type', 'source_id', 'status', 'priority', 'requested_by', 'reviewed_by', 'reviewed_at', 'notes', 'expected_by'])]
class PurchaseRequest extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'source_type' => PurchaseSourceType::class,
            'status' => PurchaseRequestStatus::class,
            'priority' => PurchaseRequestPriority::class,
            'reviewed_at' => 'datetime',
            'expected_by' => 'date',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
