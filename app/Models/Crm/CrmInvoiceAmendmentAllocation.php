<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['amendment_id', 'invoice_item_id', 'taxable_discount', 'tax_reduction', 'cgst_reduction', 'sgst_reduction', 'igst_reduction', 'cess_reduction', 'total_reduction'])]
class CrmInvoiceAmendmentAllocation extends Model
{
    protected function casts(): array
    {
        return ['taxable_discount' => 'decimal:2', 'tax_reduction' => 'decimal:2', 'cgst_reduction' => 'decimal:2', 'sgst_reduction' => 'decimal:2', 'igst_reduction' => 'decimal:2', 'cess_reduction' => 'decimal:2', 'total_reduction' => 'decimal:2'];
    }

    public function amendment(): BelongsTo { return $this->belongsTo(CrmInvoiceAmendment::class, 'amendment_id'); }
    public function invoiceItem(): BelongsTo { return $this->belongsTo(CrmInvoiceItem::class, 'invoice_item_id'); }
}
