<?php

namespace App\Models\Crm;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'branch_id', 'invoice_id', 'version_from', 'version_to', 'reason', 'amount_before', 'subtotal_added', 'discount_added', 'taxable_added', 'tax_added', 'cgst_added', 'sgst_added', 'igst_added', 'cess_added', 'amount_added', 'amount_after', 'idempotency_key', 'created_by', 'finalized_by', 'finalized_at'])]
class CrmInvoiceAmendment extends Model
{
    protected function casts(): array
    {
        return [
            'amount_before' => 'decimal:2', 'subtotal_added' => 'decimal:2', 'discount_added' => 'decimal:2',
            'taxable_added' => 'decimal:2', 'tax_added' => 'decimal:2', 'cgst_added' => 'decimal:2',
            'sgst_added' => 'decimal:2', 'igst_added' => 'decimal:2', 'cess_added' => 'decimal:2',
            'amount_added' => 'decimal:2', 'amount_after' => 'decimal:2', 'finalized_at' => 'datetime',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CrmInvoice::class, 'invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CrmInvoiceAmendmentItem::class, 'amendment_id');
    }
}
