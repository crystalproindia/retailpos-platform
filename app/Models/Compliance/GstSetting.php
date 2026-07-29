<?php

namespace App\Models\Compliance;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'legal_name', 'trade_name', 'gstin', 'registration_type', 'registered_address', 'state_code', 'state_name', 'pan', 'default_place_of_supply_state_code', 'invoice_series', 'financial_year', 'e_invoice_applicable', 'e_way_bill_applicable', 'aggregate_turnover_band', 'tax_rounding_mode', 'reverse_charge_default', 'export_type', 'accountant_reviewed_at', 'accountant_reviewed_by'])]
class GstSetting extends Model
{
    protected function casts(): array { return ['e_invoice_applicable' => 'boolean', 'e_way_bill_applicable' => 'boolean', 'reverse_charge_default' => 'boolean', 'accountant_reviewed_at' => 'datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function accountantReviewer(): BelongsTo { return $this->belongsTo(User::class, 'accountant_reviewed_by'); }
}
