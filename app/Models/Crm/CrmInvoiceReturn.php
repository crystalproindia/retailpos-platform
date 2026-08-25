<?php

namespace App\Models\Crm;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'branch_id', 'invoice_id', 'customer_id', 'credit_note_number', 'financial_year', 'issue_date', 'status', 'currency', 'gross_total', 'discount_total', 'taxable_total', 'tax_total', 'cgst_total', 'sgst_total', 'igst_total', 'cess_total', 'credit_total', 'receivable_credit_applied', 'customer_credit_due', 'known_cogs_reversal', 'known_profit_reversal', 'unavailable_cost_item_count', 'reason_code', 'reason_note', 'idempotency_key', 'company_name_snapshot', 'company_address_snapshot', 'company_tax_number_snapshot', 'customer_name_snapshot', 'customer_company_snapshot', 'customer_address_snapshot', 'customer_tax_number_snapshot', 'created_by', 'finalized_by', 'finalized_at'])]
class CrmInvoiceReturn extends Model
{
    public const STATUS_FINALIZED = 'finalized';

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'finalized_at' => 'datetime',
            'gross_total' => 'decimal:2', 'discount_total' => 'decimal:2', 'taxable_total' => 'decimal:2',
            'tax_total' => 'decimal:2', 'cgst_total' => 'decimal:2', 'sgst_total' => 'decimal:2',
            'igst_total' => 'decimal:2', 'cess_total' => 'decimal:2', 'credit_total' => 'decimal:2',
            'receivable_credit_applied' => 'decimal:2', 'customer_credit_due' => 'decimal:2',
            'known_cogs_reversal' => 'decimal:2', 'known_profit_reversal' => 'decimal:2',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CrmCustomer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CrmInvoiceReturnItem::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
