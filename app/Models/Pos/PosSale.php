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

#[Fillable(['company_id', 'branch_id', 'register_id', 'register_session_id', 'customer_id', 'customer_name_snapshot', 'customer_mobile_snapshot', 'sale_number', 'receipt_number', 'financial_year', 'timezone', 'place_of_supply_state_code', 'tax_treatment_snapshot', 'offline_uuid', 'offline_reference', 'completion_key', 'synced_from_offline', 'offline_created_at', 'device_id', 'status', 'currency', 'sale_type', 'subtotal', 'discount_amount', 'item_discount_total', 'bill_discount_total', 'taxable_amount', 'tax_amount', 'cgst_total', 'sgst_total', 'igst_total', 'cess_total', 'rounding_adjustment', 'total_amount', 'paid_amount', 'change_amount', 'balance_due', 'returned_amount', 'return_status', 'notes', 'device_type', 'held_by', 'completed_by', 'voided_by', 'held_at', 'completed_at', 'sold_at', 'voided_at', 'void_reason'])]
class PosSale extends Model
{
    protected $attributes = ['status' => 'held', 'subtotal' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'paid_amount' => 0, 'change_amount' => 0, 'device_type' => 'desktop'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'item_discount_total' => 'decimal:2', 'bill_discount_total' => 'decimal:2', 'taxable_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'cgst_total' => 'decimal:2', 'sgst_total' => 'decimal:2', 'igst_total' => 'decimal:2', 'cess_total' => 'decimal:2', 'rounding_adjustment' => 'decimal:2', 'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'change_amount' => 'decimal:2', 'balance_due' => 'decimal:2', 'returned_amount' => 'decimal:2', 'synced_from_offline' => 'boolean', 'offline_created_at' => 'datetime', 'held_at' => 'datetime', 'completed_at' => 'datetime', 'sold_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function register(): BelongsTo { return $this->belongsTo(PosRegister::class, 'register_id'); }
    public function registerSession(): BelongsTo { return $this->belongsTo(PosRegisterSession::class, 'register_session_id'); }
    public function holder(): BelongsTo { return $this->belongsTo(User::class, 'held_by'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
    public function voider(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
    public function items(): HasMany { return $this->hasMany(PosSaleItem::class); }
    public function payments(): HasMany { return $this->hasMany(PosPayment::class); }
    public function returns(): HasMany { return $this->hasMany(PosReturn::class, 'original_sale_id'); }
}
