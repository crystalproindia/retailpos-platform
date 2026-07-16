<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\QuotationStatus;
use App\Events\Domain\Crm\CrmCustomerCreated;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmQuotation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmCustomerConversionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /** @param array<string, mixed> $data */
    public function convert(CrmLead $lead, User $user, array $data, ?CrmQuotation $quotation = null): CrmCustomer
    {
        $this->ensureAvailable($lead, $quotation);

        return DB::transaction(function () use ($lead, $user, $data, $quotation): CrmCustomer {
            $customer = CrmCustomer::create(Arr::only($data, [
                'company_name', 'display_name', 'business_type', 'email', 'phone', 'country', 'state', 'city', 'billing_address', 'tax_number', 'number_of_stores', 'status', 'notes',
            ]) + [
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'quotation_id' => $quotation?->id,
                'customer_code' => $this->nextCustomerCode($lead->company_id),
                'source' => $lead->source?->name,
                'converted_at' => now(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $customer->contacts()->create([
                'name' => $data['contact_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'designation' => $data['designation'] ?? null,
                'is_primary' => true,
            ]);

            $this->markLeadConverted($lead);
            if ($quotation) {
                $quotation->update([
                    'status' => QuotationStatus::Converted,
                    'converted_at' => now(),
                    'updated_by' => $user->id,
                ]);
            }

            $activity = "Lead converted to customer {$customer->customer_code}.";
            CrmActivity::create([
                'company_id' => $lead->company_id,
                'crm_lead_id' => $lead->id,
                'assigned_user_id' => $lead->assigned_user_id,
                'created_by' => $user->id,
                'type' => ActivityType::Note,
                'subject' => $activity,
                'description' => $activity,
                'scheduled_at' => now(),
                'completed_at' => now(),
                'priority' => $lead->priority ?? LeadPriority::Medium,
            ]);
            $this->auditLogger->record('crm.customer.created', $customer, 'CRM customer created from lead', [
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'quotation_id' => $quotation?->id,
            ]);
            $this->auditLogger->record('crm.lead.converted_customer', $lead, $activity, ['customer_id' => $customer->id]);
            $this->domainEvents->dispatch(new CrmCustomerCreated(
                companyId: $lead->company_id,
                actorId: $user->id,
                aggregateType: CrmCustomer::class,
                aggregateId: $customer->id,
                payload: [
                    'customer_id' => $customer->id,
                    'customer_code' => $customer->customer_code,
                    'customer_name' => $customer->company_name,
                    'lead_id' => $lead->id,
                    'lead_title' => $lead->title,
                    'business_name' => $lead->business_name,
                    'assigned_user_id' => $lead->assigned_user_id,
                ],
            ));

            return $customer->load(['primaryContact', 'lead', 'quotation']);
        });
    }

    private function ensureAvailable(CrmLead $lead, ?CrmQuotation $quotation): void
    {
        if (CrmCustomer::query()->where('company_id', $lead->company_id)->where('lead_id', $lead->id)->exists()) {
            throw ValidationException::withMessages(['lead' => 'This lead has already been converted to a CRM customer.']);
        }

        if ($quotation && $quotation->status !== QuotationStatus::Accepted) {
            throw ValidationException::withMessages(['quotation' => 'Only accepted quotations can be converted to a customer.']);
        }
    }

    private function markLeadConverted(CrmLead $lead): void
    {
        $wonStatus = CrmLeadStatus::query()
            ->where('company_id', $lead->company_id)
            ->where('stage_type', LeadStageType::Won->value)
            ->where('is_active', true)
            ->first();

        if (! $wonStatus) {
            $wonStatus = CrmLeadStatus::firstOrCreate(
                ['company_id' => $lead->company_id, 'slug' => 'won'],
                [
                    'name' => 'Won',
                    'stage_type' => LeadStageType::Won,
                    'is_won' => true,
                    'is_active' => true,
                    'sort_order' => (int) CrmLeadStatus::query()->where('company_id', $lead->company_id)->max('sort_order') + 1,
                ],
            );
        }

        $lead->update([
            'converted_at' => now(),
            'won_at' => now(),
            'status_id' => $wonStatus->id,
        ]);
    }

    private function nextCustomerCode(int $companyId): string
    {
        $year = now()->format('Y');
        $prefix = "RPC-{$year}-";
        $lastCode = CrmCustomer::query()
            ->where('company_id', $companyId)
            ->where('customer_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->latest('id')
            ->value('customer_code');
        $lastSequence = $lastCode ? (int) substr($lastCode, -6) : 0;

        return $prefix.str_pad((string) ($lastSequence + 1), 6, '0', STR_PAD_LEFT);
    }
}
