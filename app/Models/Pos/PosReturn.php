<?php

namespace App\Models\Pos;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customers\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'branch_id', 'original_sale_id', 'exchange_sale_id', 'customer_id', 'return_number', 'credit_note_number', 'financial_year', 'return_type', 'status', 'return_date', 'timezone', 'currency', 'subtotal', 'discount_adjustment_total', 'taxable_adjustment_total', 'tax_adjustment_total', 'cgst_adjustment_total', 'sgst_adjustment_total', 'igst_adjustment_total', 'cess_adjustment_total', 'refund_total', 'store_credit_total', 'exchange_payable_total', 'exchange_refund_total', 'reason_code', 'reason_text', 'notes', 'idempotency_key', 'requested_by', 'approved_by', 'approved_at', 'completed_by', 'completed_at', 'rejected_by', 'rejected_at', 'rejection_reason'])]
class PosReturn extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        $money = ['subtotal', 'discount_adjustment_total', 'taxable_adjustment_total', 'tax_adjustment_total', 'cgst_adjustment_total', 'sgst_adjustment_total', 'igst_adjustment_total', 'cess_adjustment_total', 'refund_total', 'store_credit_total', 'exchange_payable_total', 'exchange_refund_total'];
        return array_fill_keys($money, 'decimal:2') + ['return_date' => 'date', 'approved_at' => 'datetime', 'completed_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function originalSale(): BelongsTo { return $this->belongsTo(PosSale::class, 'original_sale_id'); }
    public function exchangeSale(): BelongsTo { return $this->belongsTo(PosSale::class, 'exchange_sale_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
    public function rejecter(): BelongsTo { return $this->belongsTo(User::class, 'rejected_by'); }
    public function items(): HasMany { return $this->hasMany(PosReturnItem::class); }
    public function refunds(): HasMany { return $this->hasMany(PosRefund::class); }
}
