<?php

namespace App\Models\Crm;

use App\Enums\Crm\InvoiceStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'quotation_id', 'opportunity_id', 'lead_id', 'customer_id', 'crm_contact_id', 'invoice_number', 'billing_name', 'billing_company', 'billing_email', 'billing_phone', 'billing_address', 'billing_country', 'customer_tax_number', 'place_of_supply', 'tax_classification', 'currency', 'subtotal', 'discount_total', 'taxable_total', 'tax_total', 'adjustment_total', 'grand_total', 'amount_paid', 'balance_due', 'status', 'issue_date', 'due_date', 'notes', 'terms_conditions', 'internal_notes', 'public_token_hash', 'public_token_expires_at', 'public_token_revoked_at', 'sent_at', 'first_viewed_at', 'last_viewed_at', 'public_view_count', 'paid_at', 'cancelled_at', 'do_not_remind_before', 'created_by', 'updated_by'])]
class CrmInvoice extends Model
{
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date', 'due_date' => 'date', 'do_not_remind_before' => 'date',
            'public_token_expires_at' => 'datetime', 'public_token_revoked_at' => 'datetime', 'sent_at' => 'datetime',
            'first_viewed_at' => 'datetime', 'last_viewed_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'taxable_total' => 'decimal:2', 'tax_total' => 'decimal:2',
            'adjustment_total' => 'decimal:2', 'grand_total' => 'decimal:2', 'amount_paid' => 'decimal:2', 'balance_due' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function quotation(): BelongsTo { return $this->belongsTo(CrmQuotation::class); }
    public function opportunity(): BelongsTo { return $this->belongsTo(CrmOpportunity::class); }
    public function lead(): BelongsTo { return $this->belongsTo(CrmLead::class); }
    public function customer(): BelongsTo { return $this->belongsTo(CrmCustomer::class); }
    public function contact(): BelongsTo { return $this->belongsTo(CrmContact::class, 'crm_contact_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(CrmInvoiceItem::class, 'invoice_id')->orderBy('sort_order'); }
    public function payments(): HasMany { return $this->hasMany(CrmInvoicePayment::class, 'invoice_id')->latest('payment_date'); }
    public function isOverdue(): bool { return $this->balance_due > 0 && $this->due_date?->isPast() && ! $this->status?->isTerminal(); }
}
