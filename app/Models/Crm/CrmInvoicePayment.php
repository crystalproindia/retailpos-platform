<?php

namespace App\Models\Crm;

use App\Enums\Crm\InvoicePaymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'invoice_id', 'payment_reference', 'amount', 'currency', 'payment_date', 'payment_method', 'transaction_reference', 'bank_name', 'cheque_number', 'notes', 'status', 'receipt_number', 'recorded_by', 'cleared_by', 'cleared_at', 'reversed_by', 'reversed_at', 'reversal_reason', 'idempotency_key'])]
class CrmInvoicePayment extends Model
{
    protected function casts(): array { return ['amount' => 'decimal:2', 'payment_date' => 'date', 'status' => InvoicePaymentStatus::class, 'cleared_at' => 'datetime', 'reversed_at' => 'datetime']; }
    public function invoice(): BelongsTo { return $this->belongsTo(CrmInvoice::class, 'invoice_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function clearedBy(): BelongsTo { return $this->belongsTo(User::class, 'cleared_by'); }
    public function reversedBy(): BelongsTo { return $this->belongsTo(User::class, 'reversed_by'); }
}
