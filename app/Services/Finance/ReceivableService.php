<?php

namespace App\Services\Finance;

use App\Enums\UserRole;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
use App\Models\Crm\CrmInvoiceReturn;
use App\Models\Finance\CustomerCreditAllocation;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use App\Support\Finance\FinanceAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReceivableService
{
    private const OPEN_STATUSES = ['issued', 'sent', 'viewed', 'partially_paid', 'overdue'];

    private const VALID_STATUSES = ['issued', 'sent', 'viewed', 'partially_paid', 'paid', 'credited', 'overdue'];

    public function __construct(private readonly OutletAccessService $outlets) {}

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function dashboard(User $user, array $filters = []): array
    {
        $today = CarbonImmutable::now($user->company?->timezone ?: 'UTC')->startOfDay();
        $query = $this->openQuery($user, $filters);
        $rows = (clone $query)->with(['customer', 'lead.assignedUser'])->orderBy('due_date')->orderBy('id')->paginate(25)->withQueryString();
        $snapshot = $this->snapshot($user, $filters, $today);

        return ['invoices' => $rows, 'metrics' => $snapshot['metrics'], 'aging' => $snapshot['aging']];
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function snapshot(User $user, array $filters = [], ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now($user->company?->timezone ?: 'UTC')->startOfDay();
        $all = $this->openQuery($user, $filters)->get(['id', 'customer_id', 'billing_name', 'billing_company', 'grand_total', 'amount_paid', 'credited_total', 'balance_due', 'due_date']);
        $outstanding = $all->sum(fn (CrmInvoice $invoice): int => FinanceAmount::minor($invoice->balance_due));
        $overdue = $all->filter(fn (CrmInvoice $invoice): bool => $invoice->due_date?->lt($today) ?? false)->sum(fn (CrmInvoice $invoice): int => FinanceAmount::minor($invoice->balance_due));
        $dueToday = $all->filter(fn (CrmInvoice $invoice): bool => $invoice->due_date?->isSameDay($today) ?? false)->sum(fn (CrmInvoice $invoice): int => FinanceAmount::minor($invoice->balance_due));
        $customerIds = $all->pluck('customer_id')->filter()->unique();
        $credits = $customerIds->sum(fn (int $customerId): int => $this->availableCreditMinor($user, $customerId));

        return [
            'metrics' => ['outstanding' => $outstanding, 'due_today' => $dueToday, 'overdue' => $overdue, 'customer_credits' => $credits, 'average_collection_age' => $this->averageCollectionAge($user, $filters)],
            'aging' => $this->agingFrom($all, $today),
            'customers' => $all->groupBy(fn (CrmInvoice $invoice): string => (string) ($invoice->customer_id ?: 'legacy-'.$invoice->id))->map(function (Collection $rows): array {
                $first = $rows->first();

                return ['customer_id' => $first->customer_id, 'name' => $first->billing_company ?: $first->billing_name ?: 'Unassigned', 'document_count' => $rows->count(), 'outstanding' => $rows->sum(fn (CrmInvoice $invoice): int => FinanceAmount::minor($invoice->balance_due))];
            })->sortByDesc('outstanding')->take(5)->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    public function customerSummary(User $user, CrmCustomer $customer): array
    {
        abort_unless($customer->company_id === $user->company_id, 404);
        $today = CarbonImmutable::now($user->company?->timezone ?: 'UTC')->startOfDay();
        $invoices = $this->invoiceQuery($user)->where('customer_id', $customer->id)->whereIn('status', self::VALID_STATUSES)->get();
        $outstanding = $invoices->sum(fn (CrmInvoice $invoice): int => FinanceAmount::minor($invoice->balance_due));
        $credit = $this->availableCreditMinor($user, $customer->id);
        $limit = $customer->credit_limit === null ? null : FinanceAmount::minor($customer->credit_limit);

        return [
            'total_invoiced' => $invoices->sum(fn (CrmInvoice $invoice): int => FinanceAmount::minor($invoice->grand_total)),
            'total_paid' => $invoices->sum(fn (CrmInvoice $invoice): int => FinanceAmount::minor($invoice->amount_paid)),
            'outstanding' => $outstanding,
            'overdue' => $invoices->filter(fn (CrmInvoice $invoice): bool => FinanceAmount::minor($invoice->balance_due) > 0 && ($invoice->due_date?->lt($today) ?? false))->sum(fn (CrmInvoice $invoice): int => FinanceAmount::minor($invoice->balance_due)),
            'available_credit' => $credit,
            'refund_due' => 0,
            'credit_limit' => $limit,
            'available_limit' => $limit === null ? null : max(0, $limit - max(0, $outstanding - $credit)),
            'net_exposure' => max(0, $outstanding - $credit),
            'oldest_unpaid' => $invoices->where('balance_due', '>', 0)->sortBy(fn (CrmInvoice $invoice) => [$invoice->due_date?->toDateString() ?? '9999-12-31', $invoice->id])->first(),
        ];
    }

    /** @return array{opening:int,closing:int,rows:Collection<int,array<string,mixed>>} */
    public function statement(User $user, CrmCustomer $customer, CarbonImmutable $from, CarbonImmutable $to): array
    {
        abort_unless($customer->company_id === $user->company_id, 404);
        $all = $this->statementRows($user, $customer)->sortBy(fn (array $row): string => $row['date'].'|'.$row['sequence'])->values();
        $opening = $all->filter(fn (array $row): bool => $row['date'] < $from->toDateString())->sum(fn (array $row): int => $row['debit'] - $row['credit']);
        $running = $opening;
        $rows = $all->filter(fn (array $row): bool => $row['date'] >= $from->toDateString() && $row['date'] <= $to->toDateString())
            ->map(function (array $row) use (&$running): array {
                $running += $row['debit'] - $row['credit'];

                return $row + ['balance' => $running];
            })->values();

        return ['opening' => $opening, 'closing' => $running, 'rows' => $rows];
    }

    public function availableCreditMinor(User $user, int $customerId): int
    {
        $credits = $this->creditQuery($user)->where('customer_id', $customerId)->where('status', 'finalized')->sum('customer_credit_due');
        $used = CustomerCreditAllocation::query()->where('company_id', $user->company_id)->where('customer_id', $customerId)->sum('amount');
        $onAccount = $this->paymentQuery($user)->where('customer_id', $customerId)->whereIn('status', ['recorded', 'cleared'])->sum('unallocated_amount');

        return max(0, FinanceAmount::minor($credits) - FinanceAmount::minor($used) + FinanceAmount::minor($onAccount));
    }

    public function availableCredits(User $user, int $customerId): Collection
    {
        return $this->creditQuery($user)
            ->where('customer_id', $customerId)
            ->where('status', 'finalized')
            ->withSum('creditAllocations', 'amount')
            ->get()
            ->map(function (CrmInvoiceReturn $credit): CrmInvoiceReturn {
                $credit->setAttribute('available_amount', FinanceAmount::decimal(max(0, FinanceAmount::minor($credit->customer_credit_due) - FinanceAmount::minor($credit->credit_allocations_sum_amount ?? 0))));

                return $credit;
            })
            ->filter(fn (CrmInvoiceReturn $credit): bool => FinanceAmount::minor($credit->getAttribute('available_amount')) > 0)
            ->values();
    }

    public function openQuery(User $user, array $filters = []): Builder
    {
        return $this->invoiceQuery($user)->whereIn('status', self::OPEN_STATUSES)->where('balance_due', '>', 0)
            ->when(is_numeric($filters['outlet_id'] ?? null) && (int) $filters['outlet_id'] > 0, fn (Builder $q) => $q->where('branch_id', (int) $filters['outlet_id']))
            ->when($filters['customer_id'] ?? null, fn (Builder $q, $id) => $q->where('customer_id', (int) $id))
            ->when($filters['search'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $scope) => $scope->where('invoice_number', 'like', '%'.$this->escaped($term).'%')->orWhere('billing_name', 'like', '%'.$this->escaped($term).'%')->orWhere('billing_company', 'like', '%'.$this->escaped($term).'%')->orWhere('billing_phone', 'like', '%'.$this->escaped($term).'%')))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('issue_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('issue_date', '<=', $to))
            ->when($filters['bucket'] ?? null, fn (Builder $q, string $bucket) => $this->applyBucket($q, $bucket, CarbonImmutable::now($user->company?->timezone ?: 'UTC')->startOfDay()));
    }

    public function invoiceQuery(User $user): Builder
    {
        $outletIds = $this->authorizedOutletIds($user);
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return CrmInvoice::query()->where('company_id', $user->company_id)
            ->when(! $this->outlets->hasCompanyWideAccess($user), fn (Builder $query) => $query->whereIn('branch_id', $outletIds))
            ->when($role === UserRole::Sales, fn (Builder $q) => $q->where(fn (Builder $scope) => $scope->where('created_by', $user->id)->orWhereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id))));
    }

    public function paymentQuery(User $user): Builder
    {
        return CrmInvoicePayment::query()->where('company_id', $user->company_id)
            ->when(! $this->outlets->hasCompanyWideAccess($user), fn (Builder $query) => $query->whereIn('branch_id', $this->authorizedOutletIds($user)));
    }

    public function creditQuery(User $user): Builder
    {
        return CrmInvoiceReturn::query()->where('company_id', $user->company_id)
            ->when(! $this->outlets->hasCompanyWideAccess($user), fn (Builder $query) => $query->whereIn('branch_id', $this->authorizedOutletIds($user)));
    }

    public function authorizedOutletIds(User $user): Collection
    {
        return $this->outlets->accessibleOutlets($user, false)->pluck('id');
    }

    /** @return Collection<int,array<string,mixed>> */
    private function statementRows(User $user, CrmCustomer $customer): Collection
    {
        $invoices = $this->invoiceQuery($user)->where('customer_id', $customer->id)->whereIn('status', self::VALID_STATUSES)->get()->map(fn (CrmInvoice $invoice): array => ['date' => ($invoice->issue_date ?? $invoice->created_at)->toDateString(), 'sequence' => '1-'.$invoice->id, 'type' => 'Invoice', 'reference' => $invoice->invoice_number, 'description' => 'Sales invoice', 'debit' => FinanceAmount::minor($invoice->grand_total), 'credit' => 0, 'url' => route('sales.invoices.show', $invoice)]);
        $payments = $this->paymentQuery($user)->with(['invoice', 'allocations.invoice'])->where(fn (Builder $query) => $query->where('customer_id', $customer->id)->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('customer_id', $customer->id)))->whereIn('status', ['recorded', 'cleared'])->get()->map(function (CrmInvoicePayment $payment): array {
            $targets = $payment->allocations->pluck('invoice.invoice_number')->filter()->join(', ');

            return ['date' => $payment->payment_date->toDateString(), 'sequence' => '2-'.$payment->id, 'type' => 'Payment', 'reference' => $payment->receipt_number ?: $payment->payment_reference, 'description' => $targets ? 'Payment allocated to '.$targets : 'Payment received on account', 'debit' => 0, 'credit' => FinanceAmount::minor($payment->amount), 'url' => $payment->invoice ? route('sales.invoices.show', $payment->invoice) : route('finance.reconciliation.index')];
        });
        $credits = $this->creditQuery($user)->where('customer_id', $customer->id)->where('status', 'finalized')->get()->map(fn (CrmInvoiceReturn $return): array => ['date' => $return->issue_date->toDateString(), 'sequence' => '3-'.$return->id, 'type' => 'Credit note', 'reference' => $return->credit_note_number, 'description' => 'Approved sales return credit', 'debit' => 0, 'credit' => FinanceAmount::minor($return->credit_total), 'url' => route('sales.credit-notes.show', $return)]);

        return $invoices->concat($payments)->concat($credits);
    }

    /** @param Collection<int,CrmInvoice> $invoices @return array<string,int> */
    private function agingFrom(Collection $invoices, CarbonImmutable $today): array
    {
        $buckets = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        foreach ($invoices as $invoice) {
            $days = $invoice->due_date ? $invoice->due_date->diffInDays($today, false) : 0;
            $key = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90_plus')));
            $buckets[$key] += FinanceAmount::minor($invoice->balance_due);
        }

        return $buckets;
    }

    private function applyBucket(Builder $query, string $bucket, CarbonImmutable $today): Builder
    {
        return match ($bucket) {
            'current' => $query->where(fn (Builder $q) => $q->whereNull('due_date')->orWhereDate('due_date', '>=', $today)),
            '1_30' => $query->whereBetween('due_date', [$today->subDays(30), $today->subDay()]),
            '31_60' => $query->whereBetween('due_date', [$today->subDays(60), $today->subDays(31)]),
            '61_90' => $query->whereBetween('due_date', [$today->subDays(90), $today->subDays(61)]),
            '90_plus' => $query->whereDate('due_date', '<', $today->subDays(90)),
            default => $query,
        };
    }

    private function averageCollectionAge(User $user, array $filters): ?int
    {
        $rows = $this->invoiceQuery($user)->where('status', 'paid')->whereNotNull('paid_at')->whereNotNull('issue_date')
            ->when(is_numeric($filters['outlet_id'] ?? null) && (int) $filters['outlet_id'] > 0, fn (Builder $q) => $q->where('branch_id', (int) $filters['outlet_id']))->limit(1000)->get(['issue_date', 'paid_at']);
        if ($rows->isEmpty()) {
            return null;
        }

        return (int) round($rows->avg(fn (CrmInvoice $invoice): int => $invoice->issue_date->diffInDays($invoice->paid_at)));
    }

    private function escaped(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term));
    }
}
