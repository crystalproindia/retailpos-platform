<?php

namespace App\Repositories\Crm;

use App\Enums\UserRole;
use App\Models\Crm\CrmInvoice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoiceRepository
{
    /** @param array<string,mixed> $filters */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($user, $filters)->with(['lead', 'quotation'])
            ->latest('created_at')->paginate(15)->withQueryString();
    }

    /** @param array<string,mixed> $filters @return Collection<int, CrmInvoice> */
    public function export(User $user, array $filters = []): Collection
    {
        return $this->filteredQuery($user, $filters)
            ->with('quotation')
            ->latest('created_at')
            ->limit(5000)
            ->get();
    }

    public function find(User $user, int $invoice): CrmInvoice
    {
        return $this->queryForUser($user)->with(['items', 'payments.recorder', 'quotation', 'opportunity', 'lead.assignedUser'])->findOrFail($invoice);
    }

    /** @return Collection<int, array{currency:string,total_invoiced:string,total_collected:string,outstanding:string,overdue:string}> */
    public function collectionSummary(User $user): Collection
    {
        return $this->queryForUser($user)
            ->selectRaw("currency, SUM(grand_total) as total_invoiced, SUM(amount_paid) as total_collected, SUM(balance_due) as outstanding, SUM(CASE WHEN due_date < ? AND balance_due > 0 AND status NOT IN ('cancelled', 'void', 'paid') THEN balance_due ELSE 0 END) as overdue", [today()->toDateString()])
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn (CrmInvoice $invoice): array => [
                'currency' => $invoice->currency,
                'total_invoiced' => (string) $invoice->getAttribute('total_invoiced'),
                'total_collected' => (string) $invoice->getAttribute('total_collected'),
                'outstanding' => (string) $invoice->getAttribute('outstanding'),
                'overdue' => (string) $invoice->getAttribute('overdue'),
            ]);
    }

    private function queryForUser(User $user): Builder
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);
        return CrmInvoice::query()->where('company_id', $user->company_id)
            ->when($role === UserRole::Sales, fn (Builder $query) => $query->whereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id)));
    }

    /** @param array<string,mixed> $filters */
    private function filteredQuery(User $user, array $filters): Builder
    {
        return $this->queryForUser($user)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhere('billing_name', 'like', "%{$search}%")
                ->orWhere('billing_company', 'like', "%{$search}%")
                ->orWhere('billing_email', 'like', "%{$search}%")
                ->orWhere('billing_phone', 'like', "%{$search}%")
                ->orWhereHas('quotation', fn (Builder $quote) => $quote->where('quotation_number', 'like', "%{$search}%"))
                ->orWhereHas('payments', fn (Builder $payment) => $payment->where('transaction_reference', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
