<?php

namespace App\Models\Purchases;

use App\Enums\Purchases\PurchaseReturnStatus;
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

#[Fillable(['company_id', 'branch_id', 'warehouse_id', 'supplier_id', 'goods_receipt_id', 'return_number', 'status', 'return_date', 'reason', 'notes', 'created_by', 'approved_by', 'approved_at'])]
class PurchaseReturn extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PurchaseReturnStatus::class,
            'return_date' => 'date',
            'approved_at' => 'datetime',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
