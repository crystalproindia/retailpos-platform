<?php

namespace App\Services\Finance;

use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\Supplier;
use App\Models\Purchases\SupplierPayment;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use App\Support\Finance\FinanceAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PayableService
{
    private const OPEN_STATUSES = ['approved', 'partially_paid', 'overdue'];

    private const VALID_STATUSES = ['approved', 'partially_paid', 'overdue', 'paid'];

    public function __construct(private readonly OutletAccessService $outlets) {}

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function dashboard(User $user, array $filters = []): array
    {
        $today = CarbonImmutable::now($user->company?->timezone ?: 'UTC')->startOfDay();
        $query = $this->openQuery($user, $filters);
        $snapshot = $this->snapshot($user, $filters, $today);

        return [
            'invoices' => (clone $query)->with('supplier')->orderBy('due_date')->orderBy('id')->paginate(25)->withQueryString(),
            'metrics' => $snapshot['metrics'],
            'aging' => $snapshot['aging'],
        ];
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function snapshot(User $user, array $filters = [], ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now($user->company?->timezone ?: 'UTC')->startOfDay();
        $all = $this->openQuery($user, $filters)->with('supplier:id,name')->get(['id', 'supplier_id', 'outstanding_total', 'due_date']);

        return [
            'metrics' => [
                'payable' => $all->sum(fn (PurchaseInvoice $invoice): int => FinanceAmount::minor($invoice->outstanding_total)),
                'due_today' => $all->filter(fn (PurchaseInvoice $invoice): bool => $invoice->due_date?->isSameDay($today) ?? false)->sum(fn (PurchaseInvoice $invoice): int => FinanceAmount::minor($invoice->outstanding_total)),
                'overdue' => $all->filter(fn (PurchaseInvoice $invoice): bool => $invoice->due_date?->lt($today) ?? false)->sum(fn (PurchaseInvoice $invoice): int => FinanceAmount::minor($invoice->outstanding_total)),
                'upcoming' => $all->filter(fn (PurchaseInvoice $invoice): bool => $invoice->due_date?->gt($today) ?? false)->sum(fn (PurchaseInvoice $invoice): int => FinanceAmount::minor($invoice->outstanding_total)),
            ],
            'aging' => $this->agingFrom($all, $today),
            'suppliers' => $all->groupBy('supplier_id')->map(function (Collection $rows): array {
                $first = $rows->first();

                return ['supplier_id' => $first->supplier_id, 'name' => $first->supplier?->name ?: 'Unassigned', 'document_count' => $rows->count(), 'outstanding' => $rows->sum(fn (PurchaseInvoice $invoice): int => FinanceAmount::minor($invoice->outstanding_total))];
            })->sortByDesc('outstanding')->take(5)->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    public function supplierSummary(User $user, Supplier $supplier): array
    {
        abort_unless($supplier->company_id === $user->company_id, 404);
        $today = CarbonImmutable::now($user->company?->timezone ?: 'UTC')->startOfDay();
        $invoices = $this->invoiceQuery($user)->where('supplier_id', $supplier->id)->whereIn('status', self::VALID_STATUSES)->get();
        $payments = $this->paymentQuery($user)->where('supplier_id', $supplier->id)->where('status', 'recorded')->get();

        return [
            'total_purchases' => $invoices->sum(fn (PurchaseInvoice $invoice): int => FinanceAmount::minor($invoice->grand_total)),
            'total_paid' => $invoices->sum(fn (PurchaseInvoice $invoice): int => FinanceAmount::minor($invoice->paid_total)),
            'outstanding' => $invoices->sum(fn (PurchaseInvoice $invoice): int => FinanceAmount::minor($invoice->outstanding_total)),
            'overdue' => $invoices->filter(fn (PurchaseInvoice $invoice): bool => FinanceAmount::minor($invoice->outstanding_total) > 0 && ($invoice->due_date?->lt($today) ?? false))->sum(fn (PurchaseInvoice $invoice): int => FinanceAmount::minor($invoice->outstanding_total)),
            'supplier_credit' => $payments->sum(fn (SupplierPayment $payment): int => FinanceAmount::minor($payment->unallocated_amount)),
            'oldest_unpaid' => $invoices->where('outstanding_total', '>', 0)->sortBy(fn (PurchaseInvoice $invoice) => [$invoice->due_date?->toDateString() ?? '9999-12-31', $invoice->id])->first(),
        ];
    }

    /** @return array{opening:int,closing:int,rows:Collection<int,array<string,mixed>>} */
    public function statement(User $user, Supplier $supplier, CarbonImmutable $from, CarbonImmutable $to): array
    {
        abort_unless($supplier->company_id === $user->company_id, 404);
        $invoices = $this->invoiceQuery($user)->where('supplier_id', $supplier->id)->whereIn('status', self::VALID_STATUSES)->get()->map(fn (PurchaseInvoice $invoice): array => ['date' => $invoice->supplier_invoice_date->toDateString(), 'sequence' => '1-'.$invoice->id, 'type' => 'Purchase invoice', 'reference' => $invoice->supplier_invoice_number, 'description' => $invoice->invoice_number, 'debit' => FinanceAmount::minor($invoice->grand_total), 'credit' => 0, 'url' => route('purchases.invoices.show', $invoice)]);
        $payments = $this->paymentQuery($user)->with('allocations.invoice')->where('supplier_id', $supplier->id)->where('status', 'recorded')->get()->map(fn (SupplierPayment $payment): array => ['date' => $payment->payment_date->toDateString(), 'sequence' => '2-'.$payment->id, 'type' => 'Payment', 'reference' => $payment->payment_number, 'description' => $payment->reference ?: 'Supplier payment', 'debit' => 0, 'credit' => FinanceAmount::minor($payment->amount), 'url' => route('purchases.payments.show', $payment)]);
        $all = $invoices->concat($payments)->sortBy(fn (array $row): string => $row['date'].'|'.$row['sequence'])->values();
        $opening = $all->filter(fn (array $row): bool => $row['date'] < $from->toDateString())->sum(fn (array $row): int => $row['debit'] - $row['credit']);
        $running = $opening;
        $rows = $all->filter(fn (array $row): bool => $row['date'] >= $from->toDateString() && $row['date'] <= $to->toDateString())->map(function (array $row) use (&$running): array {
            $running += $row['debit'] - $row['credit'];

            return $row + ['balance' => $running];
        })->values();

        return ['opening' => $opening, 'closing' => $running, 'rows' => $rows];
    }

    public function openQuery(User $user, array $filters = []): Builder
    {
        return $this->invoiceQuery($user)->whereIn('status', self::OPEN_STATUSES)->where('outstanding_total', '>', 0)
            ->when(is_numeric($filters['outlet_id'] ?? null) && (int) $filters['outlet_id'] > 0, fn (Builder $q) => $q->where('branch_id', (int) $filters['outlet_id']))
            ->when($filters['supplier_id'] ?? null, fn (Builder $q, $id) => $q->where('supplier_id', (int) $id))
            ->when($filters['search'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $scope) => $scope->where('invoice_number', 'like', '%'.$this->escaped($term).'%')->orWhere('supplier_invoice_number', 'like', '%'.$this->escaped($term).'%')->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$this->escaped($term).'%'))))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('supplier_invoice_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('supplier_invoice_date', '<=', $to))
            ->when($filters['bucket'] ?? null, fn (Builder $q, string $bucket) => $this->applyBucket($q, $bucket, CarbonImmutable::now($user->company?->timezone ?: 'UTC')->startOfDay()));
    }

    public function invoiceQuery(User $user): Builder
    {
        $ids = $this->outlets->accessibleOutlets($user, false)->pluck('id');

        return PurchaseInvoice::query()->where('company_id', $user->company_id)
            ->when(! $this->outlets->hasCompanyWideAccess($user), fn (Builder $query) => $query->whereIn('branch_id', $ids));
    }

    public function paymentQuery(User $user): Builder
    {
        $ids = $this->outlets->accessibleOutlets($user, false)->pluck('id');

        return SupplierPayment::query()->where('company_id', $user->company_id)
            ->when(! $this->outlets->hasCompanyWideAccess($user), fn (Builder $query) => $query->whereIn('branch_id', $ids));
    }

    /** @param Collection<int,PurchaseInvoice> $invoices @return array<string,int> */
    private function agingFrom(Collection $invoices, CarbonImmutable $today): array
    {
        $buckets = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        foreach ($invoices as $invoice) {
            $days = $invoice->due_date ? $invoice->due_date->diffInDays($today, false) : 0;
            $key = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90_plus')));
            $buckets[$key] += FinanceAmount::minor($invoice->outstanding_total);
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

    private function escaped(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term));
    }
}
