<?php

namespace App\Policies\Crm;

use App\Enums\UserRole;
use App\Models\Crm\CrmContact;
use App\Models\User;

class CrmContactPolicy
{
    public function view(User $user, CrmContact $contact): bool
    {
        return $this->sameCompany($user, $contact)
            && (! $this->isSales($user) || $contact->assigned_user_id === $user->id);
    }

    public function update(User $user, CrmContact $contact): bool
    {
        return $user->can('crm.contacts.manage') && $this->view($user, $contact);
    }

    private function sameCompany(User $user, CrmContact $contact): bool
    {
        return (int) $user->company_id === (int) $contact->company_id;
    }

    private function isSales(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Sales;
    }
}
