<?php

namespace App\Services\Outlets;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class OutletAccessService
{
    /** @return Collection<int, Branch> */
    public function accessibleOutlets(User $user, bool $activeOnly = true): Collection
    {
        $query = Branch::query()->where('company_id', $user->company_id);
        if ($activeOnly) $query->where('is_active', true);

        if ($this->hasCompanyWideAccess($user)) return $query->orderByDesc('is_primary')->orderBy('name')->get();

        $assigned = BranchUserAssignment::query()->where('company_id', $user->company_id)->where('user_id', $user->id)->where('is_active', true)->pluck('branch_id');
        if ($assigned->isNotEmpty()) return $query->whereIn('id', $assigned)->orderByDesc('is_primary')->orderBy('name')->get();

        return $user->branch_id ? $query->whereKey($user->branch_id)->get() : new Collection;
    }

    public function current(User $user): Branch
    {
        $outlets = $this->accessibleOutlets($user);
        $selectedId = session('outlet_context_id');
        $selected = $selectedId ? $outlets->firstWhere('id', (int) $selectedId) : null;
        $outlet = $selected ?? $this->defaultFor($user, $outlets);

        if (! $outlet) throw ValidationException::withMessages(['outlet' => 'No active outlet is assigned to this user.']);
        session(['outlet_context_id' => $outlet->id]);

        return $outlet;
    }

    public function switch(User $user, int $outletId): Branch
    {
        $outlet = $this->accessibleOutlets($user)->firstWhere('id', $outletId);
        if (! $outlet) throw ValidationException::withMessages(['outlet_id' => 'That outlet is not available to this user.']);
        session(['outlet_context_id' => $outlet->id]);

        return $outlet;
    }

    public function canAccess(User $user, Branch $outlet): bool
    {
        return $outlet->company_id === $user->company_id && $outlet->is_active && $this->accessibleOutlets($user)->contains('id', $outlet->id);
    }

    public function hasCompanyWideAccess(User $user): bool
    {
        return ($user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role)) === UserRole::Administrator;
    }

    /** @param Collection<int, Branch> $outlets */
    private function defaultFor(User $user, Collection $outlets): ?Branch
    {
        $assignment = BranchUserAssignment::query()->where('company_id', $user->company_id)->where('user_id', $user->id)->where('is_active', true)->where('is_default', true)->first();

        return ($assignment ? $outlets->firstWhere('id', $assignment->branch_id) : null)
            ?? $outlets->firstWhere('id', $user->branch_id)
            ?? $outlets->firstWhere('is_primary', true)
            ?? $outlets->first();
    }
}
