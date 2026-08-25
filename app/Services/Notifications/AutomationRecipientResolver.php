<?php

namespace App\Services\Notifications;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Support\Collection;

class AutomationRecipientResolver
{
    public function __construct(private readonly OutletAccessService $outlets) {}

    /** @return Collection<int, User> */
    public function internalRecipients(int $companyId, ?int $branchId, bool $administratorsOnly = false): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('role', $administratorsOnly
                ? [UserRole::Administrator->value]
                : [UserRole::Administrator->value, UserRole::Manager->value])
            ->get()
            ->filter(function (User $user) use ($branchId): bool {
                if ($branchId === null || $user->isAdministrator()) {
                    return true;
                }

                return $this->outlets->accessibleOutlets($user, false)->contains('id', $branchId);
            })
            ->values();
    }
}
