<?php

namespace App\Repositories\Crm;

use App\Enums\UserRole;
use App\Models\Crm\CrmCompany;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmCompanyRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->queryForUser($user, ($filters['trashed'] ?? null) === 'with')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('industry', 'like', "%{$search}%");
                });
            })
            ->when($filters['industry'] ?? null, fn (Builder $query, string $industry) => $query->where('industry', $industry))
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function findForUser(User $user, int $companyId, bool $withTrashed = false): CrmCompany
    {
        return $this->queryForUser($user, $withTrashed)
            ->with(['contacts', 'leads.status', 'activities', 'notes.user', 'tags'])
            ->findOrFail($companyId);
    }

    /**
     * @return Collection<int, CrmCompany>
     */
    public function optionsForUser(User $user): Collection
    {
        return $this->queryForUser($user)->orderBy('name')->limit(100)->get();
    }

    private function queryForUser(User $user, bool $withTrashed = false): Builder
    {
        return CrmCompany::query()
            ->with(['contacts', 'assignedUser', 'tags'])
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
