<?php

namespace App\Models\Compliance;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'note_number', 'note_type', 'original_document_type', 'original_document_id', 'original_invoice_number', 'customer_name_snapshot', 'customer_gstin_snapshot', 'reason_code', 'reason_description', 'issue_date', 'financial_year', 'place_of_supply_state_code', 'currency', 'taxable_value', 'cgst_total', 'sgst_total', 'igst_total', 'cess_total', 'grand_total', 'status', 'gst_reporting_period', 'internal_notes', 'created_by', 'issued_by', 'issued_at', 'cancelled_at'])]
class GstDocumentNote extends Model
{
    protected function casts(): array { return ['issue_date' => 'date', 'issued_at' => 'datetime', 'cancelled_at' => 'datetime', 'taxable_value' => 'decimal:2', 'cgst_total' => 'decimal:2', 'sgst_total' => 'decimal:2', 'igst_total' => 'decimal:2', 'cess_total' => 'decimal:2', 'grand_total' => 'decimal:2']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function items(): HasMany { return $this->hasMany(GstDocumentNoteItem::class); }
}
