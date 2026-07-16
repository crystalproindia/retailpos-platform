<?php

namespace App\Repositories\Crm;

use App\Enums\Crm\CrmCustomerStatus;
use App\Enums\UserRole;
use App\Models\Crm\CrmCustomer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmCustomerRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->queryForUser($user)
            ->with(['primaryContact', 'lead'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('customer_code', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('contacts', fn (Builder $contacts) => $contacts->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['business_type'] ?? null, fn (Builder $query, string $businessType) => $query->where('business_type', $businessType))
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString();
    }

    public function findForUser(User $user, int $customerId): CrmCustomer
    {
        return $this->queryForUser($user)
            ->with(['primaryContact', 'contacts', 'lead.status', 'quotation.items', 'creator', 'updater', 'auditLogs.user'])
            ->findOrFail($customerId);
    }

    public function findForLead(User $user, int $leadId): ?CrmCustomer
    {
        return $this->queryForUser($user)->where('lead_id', $leadId)->first();
    }

    /** @return array{total: int, new_this_month: int, onboarding: int, active: int, latest: Collection<int, CrmCustomer>} */
    public function dashboardMetrics(User $user): array
    {
        $base = $this->queryForUser($user);

        return [
            'total' => (clone $base)->count(),
            'new_this_month' => (clone $base)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'onboarding' => (clone $base)->where('status', CrmCustomerStatus::Onboarding->value)->count(),
            'active' => (clone $base)->where('status', CrmCustomerStatus::Active->value)->count(),
            'latest' => (clone $base)->with('primaryContact')->latest('created_at')->limit(6)->get(),
        ];
    }

    /** @return Collection<int, string> */
    public function businessTypesForUser(User $user): Collection
    {
        return $this->queryForUser($user)->whereNotNull('business_type')->distinct()->orderBy('business_type')->pluck('business_type');
    }

    private function queryForUser(User $user): Builder
    {
        return CrmCustomer::query()
            ->where('company_id', $user->company_id)
            ->when($this->isSales($user), fn (Builder $query) => $query->whereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id)));
    }

    private function isSales(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Sales;
    }
}
