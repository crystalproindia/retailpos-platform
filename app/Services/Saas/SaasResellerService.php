<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\SaasReseller;
use App\Models\SaasResellerTenantAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SaasResellerService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function assign(SaasReseller $reseller, Company $company, User $actor, ?string $notes = null): SaasResellerTenantAssignment
    {
        return DB::transaction(function () use ($reseller, $company, $actor, $notes): SaasResellerTenantAssignment {
            SaasResellerTenantAssignment::query()->where('company_id', $company->id)->whereNull('unassigned_at')->lockForUpdate()
                ->update(['unassigned_at' => now()]);

            $assignment = SaasResellerTenantAssignment::create([
                'saas_reseller_id' => $reseller->id,
                'company_id' => $company->id,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'notes' => $notes,
            ]);

            $this->audit->record('saas.reseller.tenant_assigned', $assignment, 'Tenant assigned to reseller.', [
                'company_id' => $company->id,
                'reseller_id' => $reseller->id,
            ]);

            return $assignment;
        });
    }

    public function unassign(SaasResellerTenantAssignment $assignment, User $actor): void
    {
        if ($assignment->unassigned_at) {
            return;
        }

        $assignment->update(['unassigned_at' => now()]);
        $this->audit->record('saas.reseller.tenant_unassigned', $assignment, 'Tenant unassigned from reseller.', [
            'company_id' => $assignment->company_id,
            'reseller_id' => $assignment->saas_reseller_id,
            'actor_id' => $actor->id,
        ]);
    }
}
