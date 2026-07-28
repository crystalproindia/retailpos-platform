<?php

namespace App\Repositories;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class OutletRepository
{
    /** @return Collection<int, Branch> */
    public function forCompany(User $user, bool $includeArchived = true): Collection
    {
        return Branch::query()
            ->where('company_id', $user->company_id)
            ->when(! $includeArchived, fn ($query) => $query->where('is_active', true))
            ->withCount(['userAssignments as active_assignments_count' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('is_primary')->orderBy('name')->get();
    }

    public function find(User $user, int $outlet): Branch
    {
        return Branch::query()->where('company_id', $user->company_id)->findOrFail($outlet);
    }
}
