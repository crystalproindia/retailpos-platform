<?php

namespace App\Services\Finance;

use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmInvoicePayment;
use App\Models\Finance\CrmInvoicePaymentAllocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Finance\FinanceAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerPaymentAllocationService
{
    public function __construct(
        private readonly ReceivableService $receivables,
        private readonly FinanceBalanceService $balances,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string,mixed> $data */
    public function record(User $user, array $data): CrmInvoicePayment
    {
        return DB::transaction(function () use ($user, $data): CrmInvoicePayment {
            $existing = CrmInvoicePayment::query()->where('company_id', $user->company_id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing->load('allocations.invoice');
            }

            $customer = CrmCustomer::query()->where('company_id', $user->company_id)->findOrFail($data['customer_id']);
            $amount = FinanceAmount::minor($data['amount']);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }

            $payment = CrmInvoicePayment::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'invoice_id' => null,
                'customer_id' => $customer->id,
                'payment_reference' => $this->nextReference($user->company_id, 'RPOS-PAY'),
                'receipt_number' => $this->nextReference($user->company_id, 'RPOS-RCPT'),
                'amount' => FinanceAmount::decimal($amount),
                'allocated_amount' => '0.00',
                'unallocated_amount' => FinanceAmount::decimal($amount),
                'currency' => $data['currency'] ?? 'INR',
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'recorded',
                'recorded_by' => $user->id,
                'idempotency_key' => $data['idempotency_key'],
            ]);
            $this->allocate($payment, $user, $data['allocations'] ?? [], (string) $data['idempotency_key']);
            $this->audit->record('finance.customer_payment.recorded', $payment, 'Customer payment recorded', ['company_id' => $user->company_id, 'customer_id' => $customer->id]);

            return $payment->refresh()->load('allocations.invoice');
        });
    }

    /** @param array<int,array{invoice_id:int,amount:string|int|float}> $allocations */
    public function allocate(CrmInvoicePayment $payment, User $user, array $allocations, ?string $requestKey = null): CrmInvoicePayment
    {
        return DB::transaction(function () use ($payment, $user, $allocations, $requestKey): CrmInvoicePayment {
            $payment = CrmInvoicePayment::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($payment->id);
            if (in_array($payment->status?->value, ['failed', 'reversed'], true)) {
                throw ValidationException::withMessages(['payment' => 'This payment cannot be allocated.']);
            }
            $allocationKeys = collect($allocations)->mapWithKeys(fn (array $allocation): array => [
                (int) $allocation['invoice_id'] => hash('sha256', implode('|', [$requestKey ?: $payment->id, $payment->id, (int) $allocation['invoice_id'], FinanceAmount::decimal(FinanceAmount::minor($allocation['amount']))])),
            ]);
            if ($allocationKeys->isNotEmpty() && CrmInvoicePaymentAllocation::query()->where('company_id', $user->company_id)->whereIn('idempotency_key', $allocationKeys->values())->count() === $allocationKeys->count()) {
                return $payment->load('allocations.invoice');
            }
            $available = FinanceAmount::minor($payment->unallocated_amount);
            foreach ($allocations as $allocation) {
                $amount = FinanceAmount::minor($allocation['amount']);
                if ($amount <= 0 || $amount > $available) {
                    throw ValidationException::withMessages(['allocations' => 'An allocation exceeds the available payment amount.']);
                }
                $invoice = $this->receivables->openQuery($user)->whereKey($allocation['invoice_id'])->lockForUpdate()->firstOrFail();
                if ((int) $invoice->customer_id !== (int) $payment->customer_id || $invoice->currency !== $payment->currency) {
                    throw ValidationException::withMessages(['allocations' => 'Payments can only be allocated to invoices for the same customer and currency.']);
                }
                if ($amount > FinanceAmount::minor($invoice->balance_due)) {
                    throw ValidationException::withMessages(['allocations' => 'An allocation exceeds the invoice outstanding amount.']);
                }
                $key = $allocationKeys->get((int) $invoice->id);
                if (CrmInvoicePaymentAllocation::query()->where('company_id', $user->company_id)->where('idempotency_key', $key)->exists()) {
                    continue;
                }
                CrmInvoicePaymentAllocation::create(['company_id' => $user->company_id, 'branch_id' => $invoice->branch_id, 'payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => FinanceAmount::decimal($amount), 'idempotency_key' => $key, 'created_by' => $user->id]);
                $available -= $amount;
                $this->balances->refreshInvoice($invoice, $user->id);
            }
            $allocated = FinanceAmount::minor($payment->amount) - $available;
            $payment->update(['allocated_amount' => FinanceAmount::decimal($allocated), 'unallocated_amount' => FinanceAmount::decimal($available)]);
            $this->audit->record('finance.customer_payment.allocated', $payment, 'Customer payment allocation updated', ['company_id' => $user->company_id]);

            return $payment->refresh()->load('allocations.invoice');
        });
    }

    /** @return array<int,array{invoice_id:int,amount:string}> */
    public function oldestFirst(User $user, int $customerId, string|int|float $amount): array
    {
        $remaining = FinanceAmount::minor($amount);
        $result = [];
        foreach ($this->receivables->openQuery($user, ['customer_id' => $customerId])->orderBy('due_date')->orderBy('issue_date')->limit(100)->get() as $invoice) {
            if ($remaining <= 0) {
                break;
            }
            $apply = min($remaining, FinanceAmount::minor($invoice->balance_due));
            $result[] = ['invoice_id' => $invoice->id, 'amount' => FinanceAmount::decimal($apply)];
            $remaining -= $apply;
        }

        return $result;
    }

    private function nextReference(int $companyId, string $prefix): string
    {
        $number = CrmInvoicePayment::query()->where('company_id', $companyId)->lockForUpdate()->count() + 1;

        return $prefix.'-'.now()->format('Y').'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
