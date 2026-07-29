<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['gst_document_note_id', 'name', 'hsn_sac', 'quantity', 'unit', 'taxable_value', 'tax_rate', 'cgst_amount', 'sgst_amount', 'igst_amount', 'cess_amount', 'line_total', 'sort_order'])]
class GstDocumentNoteItem extends Model
{
    protected function casts(): array { return ['quantity' => 'decimal:3', 'taxable_value' => 'decimal:2', 'tax_rate' => 'decimal:3', 'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2', 'igst_amount' => 'decimal:2', 'cess_amount' => 'decimal:2', 'line_total' => 'decimal:2']; }
    public function note(): BelongsTo { return $this->belongsTo(GstDocumentNote::class, 'gst_document_note_id'); }
}
