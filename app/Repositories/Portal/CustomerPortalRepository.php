<?php

namespace App\Repositories\Portal;

use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmCustomerPortalUser;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmQuotation;
use App\Models\Crm\CrmSupportTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerPortalRepository
{
    public function dashboard(CrmCustomerPortalUser $portalUser): array
    {
        $customer = $portalUser->customer;

        return [
            'quotations' => $this->quotations($customer)->count(),
            'open_proformas' => $this->proformas($customer)->whereNotIn('status', ['paid', 'cancelled'])->count(),
            'onboardings' => $this->onboardings($customer)->whereNotIn('status', ['live', 'cancelled'])->count(),
            'open_tickets' => $this->tickets($customer)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'recent_tickets' => $this->tickets($customer)->latest('updated_at')->limit(5)->get(),
            'active_onboarding' => $this->onboardings($customer)->whereNotIn('status', ['live', 'cancelled'])->latest('created_at')->first(),
        ];
    }

    public function quotations(CrmCustomer $customer): Builder
    {
        return CrmQuotation::query()
            ->with('items')
            ->where('company_id', $customer->company_id)
            ->where(function (Builder $query) use ($customer): void {
                $query->where('id', $customer->quotation_id)
                    ->orWhere('lead_id', $customer->lead_id);
            });
    }

    public function quotation(CrmCustomer $customer, int $quotationId): CrmQuotation
    {
        return $this->quotations($customer)->findOrFail($quotationId);
    }

    public function proformas(CrmCustomer $customer): Builder
    {
        return CrmProformaInvoice::query()->with(['items', 'payments'])->where('company_id', $customer->company_id)->where('customer_id', $customer->id);
    }

    public function proforma(CrmCustomer $customer, int $proformaId): CrmProformaInvoice
    {
        return $this->proformas($customer)->findOrFail($proformaId);
    }

    public function onboardings(CrmCustomer $customer): Builder
    {
        return CrmCustomerOnboarding::query()->with(['tasks', 'onboardingNotes', 'documents'])->where('company_id', $customer->company_id)->where('customer_id', $customer->id);
    }

    public function onboarding(CrmCustomer $customer, int $onboardingId): CrmCustomerOnboarding
    {
        return $this->onboardings($customer)->findOrFail($onboardingId);
    }

    public function tickets(CrmCustomer $customer): Builder
    {
        return CrmSupportTicket::query()->where('company_id', $customer->company_id)->where('customer_id', $customer->id);
    }

    public function ticket(CrmCustomer $customer, int $ticketId): CrmSupportTicket
    {
        return $this->tickets($customer)
            ->with(['messages' => fn ($query) => $query->where('visibility', 'customer_safe')->with('creator'), 'attachments'])
            ->findOrFail($ticketId);
    }

    /** @return Collection<int, CrmLead> */
    public function serviceRequests(CrmCustomer $customer): Collection
    {
        return CrmLead::query()->with(['source', 'status'])->where('company_id', $customer->company_id)->where('customer_id', $customer->id)->whereHas('source', fn (Builder $query) => $query->where('slug', 'customer-portal'))->latest()->limit(8)->get();
    }
}
