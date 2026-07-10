<?php

namespace App\Policies\Crm;

use App\Enums\UserRole;
use App\Models\Crm\CrmActivity;
use App\Models\User;

class CrmActivityPolicy
{
    public function view(User $user, CrmActivity $activity): bool
    {
        return $this->sameCompany($user, $activity)
            && (! $this->isSales($user) || $activity->assigned_user_id === $user->id);
    }

    public function update(User $user, CrmActivity $activity): bool
    {
        return $user->can('crm.activities.manage') && $this->view($user, $activity);
    }

    private function sameCompany(User $user, CrmActivity $activity): bool
    {
        return (int) $user->company_id === (int) $activity->company_id;
    }

    private function isSales(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Sales;
    }
}
