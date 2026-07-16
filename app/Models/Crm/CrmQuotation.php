<?php

namespace App\Models\Crm;

use App\Enums\Crm\QuotationStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['lead_id', 'company_id', 'quotation_number', 'title', 'customer_name', 'customer_company', 'customer_email', 'customer_phone', 'billing_address', 'currency', 'subtotal', 'discount_total', 'tax_total', 'grand_total', 'valid_until', 'status', 'notes', 'terms_conditions', 'internal_remarks', 'public_token', 'public_url', 'sent_at', 'accepted_at', 'rejected_at', 'converted_at', 'created_by', 'updated_by'])]
class CrmQuotation extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'valid_until' => 'date',
            'status' => QuotationStatus::class,
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CrmQuotationItem::class, 'quotation_id')->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    public function isExpired(): bool
    {
        return $this->valid_until?->isPast() && $this->status === QuotationStatus::Sent;
    }
}
