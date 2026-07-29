<?php

namespace App\Services\Purchases;

use App\Models\Purchases\PurchaseApprovalLog;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\SupplierPayment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurchaseNumberService $numbers,
    ) {}

    /** @param array<string, mixed> $data */
    public function record(User $user, array $data): SupplierPayment
    {
        return DB::transaction(function () use ($user, $data): SupplierPayment {
            if (! empty($data['idempotency_key'])) {
                $existing = SupplierPayment::query()->where('company_id', $user->company_id)->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }

            $amount = $this->paise($data['amount']);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }
            $payment = SupplierPayment::create([
                'company_id' => $user->company_id,
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $data['branch_id'] ?? $user->branch_id,
                'payment_number' => $this->numbers->next($user->company_id, 'payment'),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'payment_date' => $data['payment_date'],
                'currency' => $data['currency'] ?? 'INR',
                'payment_type' => $data['payment_type'] ?? 'invoice_payment',
                'payment_method' => $data['payment_method'],
                'amount' => $this->decimal($amount),
                'unallocated_amount' => $this->decimal($amount),
                'reference' => $data['reference'] ?? null,
                'cheque_number' => $data['cheque_number'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
                'status' => 'recorded',
            ]);
            $this->allocate($payment, $user, $data['allocations'] ?? []);
            $this->audit->record('supplier.payment.recorded', $payment, 'Supplier payment recorded.');

            return $payment->refresh()->load(['supplier', 'allocations.invoice']);
        });
    }

    /** @param array<int, array{purchase_invoice_id:int,amount:string|int|float}> $allocations */
    public function allocate(SupplierPayment $payment, User $user, array $allocations): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $user, $allocations): SupplierPayment {
            $payment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'reversed') {
                throw ValidationException::withMessages(['payment' => 'A reversed payment cannot be allocated.']);
            }
            $available = $this->paise($payment->unallocated_amount);
            foreach ($allocations as $allocation) {
                $amount = $this->paise($allocation['amount']);
                if ($amount <= 0 || $amount > $available) {
                    throw ValidationException::withMessages(['allocations' => 'An allocation exceeds the available payment amount.']);
                }
                $invoice = PurchaseInvoice::query()->where('company_id', $payment->company_id)->lockForUpdate()->findOrFail($allocation['purchase_invoice_id']);
                if ($invoice->supplier_id !== $payment->supplier_id || ! in_array($invoice->status, ['approved', 'partially_paid', 'overdue'], true)) {
                    throw ValidationException::withMessages(['allocations' => 'Payments can only be allocated to approved invoices for the same supplier.']);
                }
                $outstanding = $this->paise($invoice->outstanding_total);
                if ($amount > $outstanding) {
                    throw ValidationException::withMessages(['allocations' => 'An allocation exceeds the invoice outstanding amount.']);
                }
                $payment->allocations()->create(['purchase_invoice_id' => $invoice->id, 'amount' => $this->decimal($amount)]);
                $paid = $this->paise($invoice->paid_total) + $amount;
                $remaining = $outstanding - $amount;
                $invoice->update(['paid_total' => $this->decimal($paid), 'outstanding_total' => $this->decimal($remaining), 'status' => $remaining === 0 ? 'paid' : 'partially_paid']);
                $available -= $amount;
            }
            $payment->update(['unallocated_amount' => $this->decimal($available)]);
            return $payment->refresh();
        });
    }

    public function reverse(SupplierPayment $payment, User $user, string $reason): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $user, $reason): SupplierPayment {
            $payment = SupplierPayment::query()->with('allocations')->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'reversed') {
                return $payment;
            }
            foreach ($payment->allocations as $allocation) {
                $invoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail($allocation->purchase_invoice_id);
                $reversed = $this->paise($allocation->amount);
                $paid = max(0, $this->paise($invoice->paid_total) - $reversed);
                $outstanding = $this->paise($invoice->outstanding_total) + $reversed;
                $invoice->update(['paid_total' => $this->decimal($paid), 'outstanding_total' => $this->decimal($outstanding), 'status' => 'approved']);
            }
            $payment->allocations()->delete();
            $payment->update(['status' => 'reversed', 'unallocated_amount' => '0.00', 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason]);
            PurchaseApprovalLog::create(['company_id' => $payment->company_id, 'approvable_type' => SupplierPayment::class, 'approvable_id' => $payment->id, 'action' => 'reversed', 'from_status' => 'recorded', 'to_status' => 'reversed', 'user_id' => $user->id, 'comments' => $reason]);
            $this->audit->record('supplier.payment.reversed', $payment, 'Supplier payment reversed.');
            return $payment->refresh();
        });
    }

    private function paise(string|int|float $value): int { return (int) round(((float) $value) * 100); }
    private function decimal(int $paise): string { return number_format($paise / 100, 2, '.', ''); }
}
