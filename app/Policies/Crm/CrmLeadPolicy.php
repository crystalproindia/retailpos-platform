<?php

namespace App\Policies\Crm;

use App\Enums\UserRole;
use App\Models\Crm\CrmLead;
use App\Models\User;

class CrmLeadPolicy
{
    public function view(User $user, CrmLead $lead): bool
    {
        return $this->sameCompany($user, $lead)
            && (! $this->isSales($user) || $lead->assigned_user_id === $user->id || $lead->created_by === $user->id);
    }

    public function update(User $user, CrmLead $lead): bool
    {
        return $user->can('crm.leads.update') && $this->view($user, $lead);
    }

    public function delete(User $user, CrmLead $lead): bool
    {
        return $user->can('crm.leads.delete') && $this->sameCompany($user, $lead);
    }

    public function restore(User $user, CrmLead $lead): bool
    {
        return $this->delete($user, $lead);
    }

    public function convert(User $user, CrmLead $lead): bool
    {
        return $user->can('crm.leads.convert') && $this->view($user, $lead);
    }

    private function sameCompany(User $user, CrmLead $lead): bool
    {
        return (int) $user->company_id === (int) $lead->company_id;
    }

    private function isSales(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Sales;
    }
}
