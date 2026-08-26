<?php

namespace App\Services\Finance;

use App\Models\Finance\CustomerCreditAllocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Finance\FinanceAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerCreditService
{
    public function __construct(private readonly ReceivableService $receivables, private readonly FinanceBalanceService $balances, private readonly AuditLogger $audit) {}

    public function apply(User $user, int $returnId, int $invoiceId, string|int|float $amount, string $idempotencyKey): CustomerCreditAllocation
    {
        return DB::transaction(function () use ($user, $returnId, $invoiceId, $amount, $idempotencyKey): CustomerCreditAllocation {
            $existing = CustomerCreditAllocation::query()->where('company_id', $user->company_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
            $credit = $this->receivables->creditQuery($user)->where('status', 'finalized')->lockForUpdate()->findOrFail($returnId);
            $invoice = $this->receivables->openQuery($user)->whereKey($invoiceId)->lockForUpdate()->firstOrFail();
            if ((int) $credit->customer_id !== (int) $invoice->customer_id) {
                throw ValidationException::withMessages(['credit' => 'Credit and invoice must belong to the same customer.']);
            }
            $requested = FinanceAmount::minor($amount);
            $used = FinanceAmount::minor(CustomerCreditAllocation::query()->where('crm_invoice_return_id', $credit->id)->sum('amount'));
            $available = FinanceAmount::minor($credit->customer_credit_due) - $used;
            if ($requested <= 0 || $requested > $available || $requested > FinanceAmount::minor($invoice->balance_due)) {
                throw ValidationException::withMessages(['amount' => 'Credit cannot exceed the available credit or invoice balance.']);
            }
            $allocation = CustomerCreditAllocation::create(['company_id' => $user->company_id, 'branch_id' => $invoice->branch_id, 'customer_id' => $invoice->customer_id, 'crm_invoice_return_id' => $credit->id, 'invoice_id' => $invoice->id, 'amount' => FinanceAmount::decimal($requested), 'idempotency_key' => $idempotencyKey, 'created_by' => $user->id]);
            $this->balances->refreshInvoice($invoice, $user->id);
            $this->audit->record('finance.customer_credit.applied', $allocation, 'Customer credit applied to invoice', ['company_id' => $user->company_id, 'invoice_id' => $invoice->id]);

            return $allocation;
        });
    }
}
