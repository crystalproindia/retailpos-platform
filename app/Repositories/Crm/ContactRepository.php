<?php

namespace App\Repositories\Crm;

use App\Enums\UserRole;
use App\Models\Crm\CrmContact;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ContactRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->queryForUser($user, ($filters['trashed'] ?? null) === 'with')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['crm_company_id'] ?? null, fn (Builder $query, int|string $companyId) => $query->where('crm_company_id', $companyId))
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function findForUser(User $user, int $contactId, bool $withTrashed = false): CrmContact
    {
        return $this->queryForUser($user, $withTrashed)
            ->with(['leads.status', 'activities', 'timelineNotes.user', 'tags'])
            ->findOrFail($contactId);
    }

    /**
     * @return Collection<int, CrmContact>
     */
    public function optionsForUser(User $user): Collection
    {
        return $this->queryForUser($user)->orderBy('first_name')->limit(100)->get();
    }

    private function queryForUser(User $user, bool $withTrashed = false): Builder
    {
        return CrmContact::query()
            ->with(['crmCompany', 'assignedUser', 'tags'])
            ->where('company_id', $user->company_id)
            ->when($withTrashed, fn (Builder $query) => $query->withTrashed())
            ->when($this->isSales($user), fn (Builder $query) => $query->where('assigned_user_id', $user->id));
    }

    private function isSales(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Sales;
    }
}
