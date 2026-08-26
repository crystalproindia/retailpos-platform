<?php

namespace App\Models\Crm;

use App\Enums\Crm\InvoicePaymentStatus;
use App\Models\Branch;
use App\Models\Finance\CrmInvoicePaymentAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'branch_id', 'invoice_id', 'customer_id', 'payment_reference', 'amount', 'allocated_amount', 'unallocated_amount', 'currency', 'payment_date', 'payment_method', 'transaction_reference', 'bank_name', 'cheque_number', 'notes', 'status', 'receipt_number', 'recorded_by', 'cleared_by', 'cleared_at', 'reversed_by', 'reversed_at', 'reversal_reason', 'idempotency_key'])]
class CrmInvoicePayment extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'allocated_amount' => 'decimal:2', 'unallocated_amount' => 'decimal:2', 'payment_date' => 'date', 'status' => InvoicePaymentStatus::class, 'cleared_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CrmInvoice::class, 'invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CrmCustomer::class, 'customer_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CrmInvoicePaymentAllocation::class, 'payment_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
