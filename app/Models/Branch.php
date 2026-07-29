<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Purchases\GoodsReceipt;
use App\Models\Purchases\PurchaseOrder;
use App\Models\Purchases\PurchaseRequest;
use App\Models\Purchases\PurchaseReturn;
use App\Models\Promotions\PromotionBranchTarget;
use App\Models\Pos\PosSale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'legal_name', 'code', 'email', 'phone', 'address', 'city', 'state', 'postal_code', 'country', 'country_code', 'tax_number', 'timezone', 'currency', 'invoice_prefix', 'receipt_prefix', 'is_primary', 'is_active', 'created_by', 'updated_by', 'archived_at'])]
class Branch extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
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

    public function promotionTargets(): HasMany
    {
        return $this->hasMany(PromotionBranchTarget::class);
    }

    public function posSales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(BranchUserAssignment::class);
    }
}
