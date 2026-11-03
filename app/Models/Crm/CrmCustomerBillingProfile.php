<?php

namespace App\Models\Crm;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'customer_id', 'profile_name', 'is_primary', 'legal_name', 'trade_name', 'tax_number', 'billing_address', 'city', 'state', 'postal_code', 'country', 'email', 'phone', 'status'])]
class CrmCustomerBillingProfile extends Model
{
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CrmCustomer::class, 'customer_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @return array<string, string|null> */
    public function invoiceSnapshot(): array
    {
        return [
            'billing_name' => $this->trade_name ?: $this->legal_name,
            'billing_company' => $this->legal_name,
            'billing_email' => $this->email,
            'billing_phone' => $this->phone,
            'billing_address' => $this->billing_address,
            'billing_country' => $this->country,
            'customer_tax_number' => $this->tax_number,
        ];
    }
}
