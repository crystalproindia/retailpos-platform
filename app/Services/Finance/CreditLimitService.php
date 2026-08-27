<?php

namespace App\Services\Finance;

use App\Models\Crm\CrmInvoice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Finance\FinanceAmount;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreditLimitService
{
    public function __construct(
        private readonly ReceivableService $receivables,
        private readonly AuditLogger $audit,
    ) {}

    public function assertCanIssue(CrmInvoice $invoice, User $user, bool $override = false, ?string $reason = null): void
    {
        $customer = $invoice->customer;
        if (! $customer || $customer->credit_limit === null) {
            return;
        }

        $summary = $this->receivables->customerSummary($user, $customer);
        $projected = $summary['net_exposure'] + FinanceAmount::minor($invoice->grand_total);
        $limit = FinanceAmount::minor($customer->credit_limit);
        if ($projected <= $limit) {
            return;
        }

        if (! $override || ! Gate::forUser($user)->allows('finance.credit-limits.override')) {
            throw ValidationException::withMessages(['credit_limit' => 'This invoice would exceed the customer credit limit. Ask an authorized manager to review it.']);
        }
        if (mb_strlen(trim((string) $reason)) < 5) {
            throw ValidationException::withMessages(['credit_limit_override_reason' => 'Enter a clear reason for the credit-limit override.']);
        }

        $this->audit->record('finance.customer_credit_limit.overridden', $invoice, 'Customer credit limit overridden for invoice issue', [
            'company_id' => $invoice->company_id,
            'customer_id' => $customer->id,
            'credit_limit_minor' => $limit,
            'projected_exposure_minor' => $projected,
            'reason' => trim((string) $reason),
        ]);
    }

    public function assertCanIncreaseExposure(CrmInvoice $invoice, User $user, int $additionalMinor, bool $override = false, ?string $reason = null): void
    {
        $customer = $invoice->customer;
        if (! $customer || $customer->credit_limit === null || $additionalMinor <= 0) {
            return;
        }

        $summary = $this->receivables->customerSummary($user, $customer);
        $projected = $summary['net_exposure'] + $additionalMinor;
        $limit = FinanceAmount::minor($customer->credit_limit);
        if ($projected <= $limit) {
            return;
        }

        if (! $override || ! Gate::forUser($user)->allows('finance.credit-limits.override')) {
            throw ValidationException::withMessages(['credit_limit' => 'This amendment would exceed the customer credit limit. Ask an authorized manager to review it.']);
        }
        if (mb_strlen(trim((string) $reason)) < 5) {
            throw ValidationException::withMessages(['credit_limit_override_reason' => 'Enter a clear reason for the credit-limit override.']);
        }

        $this->audit->record('finance.customer_credit_limit.overridden', $invoice, 'Customer credit limit overridden for invoice amendment', [
            'company_id' => $invoice->company_id,
            'customer_id' => $customer->id,
            'credit_limit_minor' => $limit,
            'additional_exposure_minor' => $additionalMinor,
            'projected_exposure_minor' => $projected,
            'reason' => trim((string) $reason),
        ]);
    }
}
