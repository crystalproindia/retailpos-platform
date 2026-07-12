<?php

namespace App\Models\Purchases;

use App\Enums\Purchases\GoodsReceiptStatus;
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

#[Fillable(['company_id', 'branch_id', 'warehouse_id', 'supplier_id', 'purchase_order_id', 'grn_number', 'receipt_date', 'status', 'received_by', 'checked_by', 'checked_at', 'supplier_invoice_number', 'supplier_invoice_date', 'notes'])]
class GoodsReceipt extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'receipt_date' => 'date',
            'checked_at' => 'datetime',
            'supplier_invoice_date' => 'date',
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
