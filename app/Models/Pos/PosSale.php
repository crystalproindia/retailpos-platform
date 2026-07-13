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

#[Fillable(['company_id', 'branch_id', 'customer_id', 'sale_number', 'offline_uuid', 'offline_reference', 'synced_from_offline', 'offline_created_at', 'device_id', 'status', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'paid_amount', 'change_amount', 'notes', 'device_type', 'held_by', 'completed_by', 'held_at', 'completed_at'])]
class PosSale extends Model
{
    protected $attributes = ['status' => 'held', 'subtotal' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'paid_amount' => 0, 'change_amount' => 0, 'device_type' => 'desktop'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'change_amount' => 'decimal:2', 'synced_from_offline' => 'boolean', 'offline_created_at' => 'datetime', 'held_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function holder(): BelongsTo { return $this->belongsTo(User::class, 'held_by'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
    public function items(): HasMany { return $this->hasMany(PosSaleItem::class); }
    public function payments(): HasMany { return $this->hasMany(PosPayment::class); }
}
