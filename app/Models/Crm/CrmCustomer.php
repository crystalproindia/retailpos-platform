<?php

namespace App\Models\Crm;

use App\Enums\Crm\CrmCustomerStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['company_id', 'lead_id', 'quotation_id', 'customer_code', 'company_name', 'display_name', 'business_type', 'email', 'phone', 'country', 'state', 'city', 'billing_address', 'tax_number', 'number_of_stores', 'status', 'source', 'notes', 'converted_at', 'created_by', 'updated_by'])]
class CrmCustomer extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'status' => CrmCustomerStatus::class,
            'converted_at' => 'datetime',
            'number_of_stores' => 'integer',
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

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(CrmQuotation::class, 'quotation_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmCustomerContact::class, 'customer_id');
    }

    public function proformas(): HasMany
    {
        return $this->hasMany(CrmProformaInvoice::class, 'customer_id')->latest('created_at');
    }

    public function onboardings(): HasMany
    {
        return $this->hasMany(CrmCustomerOnboarding::class, 'customer_id')->latest('created_at');
    }

    public function activeOnboarding(): HasOne
    {
        return $this->hasOne(CrmCustomerOnboarding::class, 'customer_id')
            ->whereNotIn('status', ['live', 'cancelled'])
            ->latestOfMany('created_at');
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(CrmCustomerContact::class, 'customer_id')->where('is_primary', true);
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
}
