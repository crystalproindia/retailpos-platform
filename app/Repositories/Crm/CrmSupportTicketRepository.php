<?php

namespace App\Repositories\Crm;

use App\Enums\Crm\SupportTicketStatus;
use App\Enums\UserRole;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmSupportTicket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmSupportTicketRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, ['priority', 'due_at', 'created_at', 'updated_at'], true) ? $filters['sort'] : 'updated_at';
        return $this->queryForUser($user)->with(['customer', 'assignee'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('ticket_number', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%")->orWhere('reported_by_phone', 'like', "%{$search}%")->orWhere('reported_by_email', 'like', "%{$search}%")->orWhereHas('customer', fn (Builder $customer) => $customer->where('company_name', 'like', "%{$search}%")->orWhere('display_name', 'like', "%{$search}%"))->orWhereHas('onboarding', fn (Builder $onboarding) => $onboarding->where('onboarding_number', 'like', "%{$search}%"))->orWhereHas('proforma', fn (Builder $proforma) => $proforma->where('proforma_number', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $value) => $query->where('priority', $value))
            ->when($filters['category'] ?? null, fn (Builder $query, string $value) => $query->where('category', $value))
            ->when($filters['assigned_to'] ?? null, fn (Builder $query, int $value) => $query->where('assigned_to', $value))
            ->when($filters['source'] ?? null, fn (Builder $query, string $value) => $query->where('source', $value))
            ->when($filters['overdue'] ?? null, fn (Builder $query) => $query->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value])->where('due_at', '<', now()))
            ->when($filters['unresolved'] ?? null, fn (Builder $query) => $query->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value]))
            ->when($filters['created_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($sort === 'priority', fn (Builder $query) => $query->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end"))
            ->orderBy($sort, $sort === 'due_at' ? 'asc' : 'desc')->paginate(15)->withQueryString();
    }

    public function find(User $user, int $id): CrmSupportTicket
    {
        return $this->queryForUser($user)->with(['customer.primaryContact', 'lead', 'onboarding', 'proforma', 'assignee', 'creator', 'messages.creator', 'attachments.uploader', 'statusHistories.changer'])->findOrFail($id);
    }

    /** @return Collection<int, CrmCustomer> */
    public function customersForUser(User $user): Collection
    {
        return CrmCustomer::query()->where('company_id', $user->company_id)
            ->when($this->isSales($user), fn (Builder $query) => $query->whereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id)))
            ->orderBy('company_name')->get(['id', 'lead_id', 'company_name', 'display_name', 'email', 'phone']);
    }

    /** @return array<string, mixed> */
    public function dashboard(User $user): array
    {
        $base = $this->queryForUser($user); $open = (clone $base)->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value]);
        return ['open' => (clone $open)->count(), 'urgent' => (clone $open)->where('priority', 'urgent')->count(), 'overdue' => (clone $open)->where('due_at', '<', now())->count(), 'waiting_for_customer' => (clone $open)->where('status', SupportTicketStatus::WaitingForCustomer->value)->count(), 'waiting_for_internal_team' => (clone $open)->where('status', SupportTicketStatus::WaitingForInternalTeam->value)->count(), 'resolved_this_month' => (clone $base)->whereNotNull('resolved_at')->whereBetween('resolved_at', [now()->startOfMonth(), now()->endOfMonth()])->count(), 'recent' => (clone $base)->with(['customer', 'assignee'])->latest('updated_at')->limit(5)->get()];
    }

    /** @return array{open: int, recent: Collection<int, CrmSupportTicket>} */
    public function customerSummary(int $companyId, int $customerId): array { $base = CrmSupportTicket::query()->where('company_id', $companyId)->where('customer_id', $customerId); return ['open' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->count(), 'recent' => $base->with('assignee')->latest('updated_at')->limit(4)->get()]; }
    /** @return array{open: int, recent: Collection<int, CrmSupportTicket>} */
    public function onboardingSummary(int $companyId, int $onboardingId): array { $base = CrmSupportTicket::query()->where('company_id', $companyId)->where('onboarding_id', $onboardingId); return ['open' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->count(), 'recent' => $base->with('assignee')->latest('updated_at')->limit(4)->get()]; }

    private function queryForUser(User $user): Builder { return CrmSupportTicket::query()->where('company_id', $user->company_id)->when($this->isSales($user), fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('created_by', $user->id)->orWhere('assigned_to', $user->id)->orWhereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id))->orWhereHas('customer.lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id)))); }
    private function isSales(User $user): bool { $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role); return $role === UserRole::Sales; }
}
