<?php

namespace App\Services\Purchases;

use App\Models\Purchases\PurchaseApprovalLog;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\Supplier;
use App\Models\Purchases\SupplierPayment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurchaseNumberService $numbers,
        private readonly OutletAccessService $outlets,
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
            Supplier::query()->where('company_id', $user->company_id)->findOrFail($data['supplier_id']);
            $branchId = (int) ($data['branch_id'] ?? $user->branch_id);
            $branch = $user->company->branches()->findOrFail($branchId);
            abort_unless($this->outlets->canAccess($user, $branch), 403);
            $payment = SupplierPayment::create([
                'company_id' => $user->company_id,
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $branch->id,
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
            $payment = SupplierPayment::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($payment->id);
            $this->assertPaymentAccess($payment, $user);
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
                if ($invoice->branch_id !== null) {
                    $branch = $invoice->branch()->firstOrFail();
                    abort_unless($this->outlets->canAccess($user, $branch), 403);
                } else {
                    abort_unless($this->outlets->hasCompanyWideAccess($user), 403);
                }
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
            $payment = SupplierPayment::query()->where('company_id', $user->company_id)->with('allocations')->lockForUpdate()->findOrFail($payment->id);
            $this->assertPaymentAccess($payment, $user);
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

    private function paise(string|int|float $value): int
    {
        $value = trim((string) $value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = preg_replace('/\D/', '', $fraction) ?: '';
        $paise = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        if (isset($fraction[2]) && $fraction[2] >= '5') {
            $paise++;
        }

        return $negative ? -$paise : $paise;
    }

    private function assertPaymentAccess(SupplierPayment $payment, User $user): void
    {
        if ($payment->branch_id === null) {
            abort_unless($this->outlets->hasCompanyWideAccess($user), 403);

            return;
        }
        $branch = $user->company->branches()->findOrFail($payment->branch_id);
        abort_unless($this->outlets->canAccess($user, $branch), 403);
    }

    private function decimal(int $paise): string
    {
        $negative = $paise < 0;
        $digits = str_pad((string) abs($paise), 3, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);
    }
}
