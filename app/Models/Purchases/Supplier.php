<?php

namespace App\Models\Purchases;

use App\Enums\Purchases\SupplierType;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'code', 'name', 'legal_name', 'display_name', 'supplier_type', 'tax_id', 'gstin', 'pan', 'website', 'email', 'phone', 'alternate_phone', 'payment_terms', 'credit_limit', 'default_currency', 'lead_time_days', 'rating', 'manual_rating', 'service_notes', 'notes', 'is_active'])]
class Supplier extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'supplier_type' => SupplierType::class,
            'credit_limit' => 'decimal:2',
            'rating' => 'decimal:2',
            'manual_rating' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(SupplierAddress::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function scoreSnapshots(): HasMany
    {
        return $this->hasMany(SupplierScoreSnapshot::class)->latest('calculated_at');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }
}
