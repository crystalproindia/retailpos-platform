<?php

namespace App\Policies\Crm;

use App\Enums\UserRole;
use App\Models\Crm\CrmCompany;
use App\Models\User;

class CrmCompanyPolicy
{
    public function view(User $user, CrmCompany $crmCompany): bool
    {
        return $this->sameCompany($user, $crmCompany)
            && (! $this->isSales($user) || $crmCompany->assigned_user_id === $user->id);
    }

    public function update(User $user, CrmCompany $crmCompany): bool
    {
        return $user->can('crm.companies.manage') && $this->view($user, $crmCompany);
    }

    private function sameCompany(User $user, CrmCompany $crmCompany): bool
    {
        return (int) $user->company_id === (int) $crmCompany->company_id;
    }

    private function isSales(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Sales;
    }
}
