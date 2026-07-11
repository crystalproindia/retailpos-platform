<?php

namespace App\Services\Crm;

use App\Enums\Crm\LeadStageType;
use App\Events\Domain\Crm\LeadConverted;
use App\Models\Crm\CrmCompany;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Facades\DB;

class LeadConversionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function convert(CrmLead $lead, User $user, array $data = []): CrmLead
    {
        $converted = DB::transaction(function () use ($lead, $user, $data): CrmLead {
            $crmCompany = $this->resolveCompany($lead, $user, $data);
            $contact = $this->resolveContact($lead, $user, $crmCompany, $data);
            $wonStatus = CrmLeadStatus::query()
                ->where('company_id', $lead->company_id)
                ->where('stage_type', LeadStageType::Won->value)
                ->first();

            $lead->update([
                'crm_company_id' => $crmCompany->id,
                'crm_contact_id' => $contact->id,
                'status_id' => $wonStatus?->id ?? $lead->status_id,
                'converted_at' => now(),
            ]);

            $lead->notes()->create([
                'company_id' => $lead->company_id,
                'user_id' => $user->id,
                'body' => 'Lead converted to CRM company and contact.',
            ]);

            $this->auditLogger->record('crm.lead.converted', $lead, 'CRM lead converted', [
                'crm_company_id' => $crmCompany->id,
                'crm_contact_id' => $contact->id,
            ]);

            return $lead->refresh()->load(['crmCompany', 'contact', 'status']);
        });

        $this->domainEvents->dispatch(new LeadConverted(
            companyId: $converted->company_id,
            actorId: $user->id,
            aggregateType: CrmLead::class,
            aggregateId: $converted->id,
            payload: [
                'lead_id' => $converted->id,
                'lead_title' => $converted->title,
                'business_name' => $converted->business_name,
                'assigned_user_id' => $converted->assigned_user_id,
                'crm_company_id' => $converted->crm_company_id,
                'crm_contact_id' => $converted->crm_contact_id,
                'status_id' => $converted->status_id,
            ],
        ));

        return $converted;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCompany(CrmLead $lead, User $user, array $data): CrmCompany
    {
        if (! empty($data['crm_company_id'])) {
            return CrmCompany::query()
                ->where('company_id', $lead->company_id)
                ->findOrFail($data['crm_company_id']);
        }

        $name = $data['company_name'] ?? $lead->business_name ?? $lead->title;

        return CrmCompany::query()->firstOrCreate(
            [
                'company_id' => $lead->company_id,
                'name' => $name,
            ],
            [
                'branch_id' => $lead->branch_id,
                'assigned_user_id' => $lead->assigned_user_id ?? $user->id,
                'industry' => $lead->industry,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'estimated_value' => $lead->expected_value,
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveContact(CrmLead $lead, User $user, CrmCompany $crmCompany, array $data): CrmContact
    {
        if (! empty($data['crm_contact_id'])) {
            return CrmContact::query()
                ->where('company_id', $lead->company_id)
                ->findOrFail($data['crm_contact_id']);
        }

        $contactName = trim($data['contact_name'] ?? $lead->contact_name ?? 'Primary Contact');
        [$firstName, $lastName] = array_pad(explode(' ', $contactName, 2), 2, null);

        $lookup = [
            'company_id' => $lead->company_id,
            'email' => $lead->email,
        ];

        if (empty($lead->email)) {
            $lookup = [
                'company_id' => $lead->company_id,
                'crm_company_id' => $crmCompany->id,
                'phone' => $lead->phone,
            ];
        }

        return CrmContact::query()->firstOrCreate($lookup, [
            'branch_id' => $lead->branch_id,
            'crm_company_id' => $crmCompany->id,
            'assigned_user_id' => $lead->assigned_user_id ?? $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $lead->phone,
            'preferred_contact_method' => 'phone',
            'is_primary' => true,
        ]);
    }
}
