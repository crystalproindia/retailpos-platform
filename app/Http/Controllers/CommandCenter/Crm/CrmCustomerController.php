<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\CrmCustomerStatus;
use App\Enums\Crm\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCrmCustomerConversionRequest;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmQuotation;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Repositories\Crm\LeadRepository;
use App\Repositories\Crm\QuotationRepository;
use App\Services\Crm\CrmCustomerConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrmCustomerController extends Controller
{
    public function index(Request $request, CrmCustomerRepository $customers): View
    {
        return view('command-center.crm.customers.index', [
            'customers' => $customers->paginateForUser($request->user(), $request->only(['search', 'status', 'business_type'])),
            'statuses' => CrmCustomerStatus::cases(),
            'businessTypes' => $customers->businessTypesForUser($request->user()),
        ]);
    }

    public function show(Request $request, CrmCustomerRepository $customers, int $customer): View
    {
        return view('command-center.crm.customers.show', ['customer' => $customers->findForUser($request->user(), $customer)]);
    }

    public function createForLead(Request $request, LeadRepository $leads, int $lead): View
    {
        return $this->conversionForm($request, $leads->findForUser($request->user(), $lead));
    }

    public function createForQuotation(Request $request, QuotationRepository $quotations, int $quotation): View
    {
        $crmQuotation = $quotations->findForUser($request->user(), $quotation);
        if ($crmQuotation->status !== QuotationStatus::Accepted) {
            throw ValidationException::withMessages(['quotation' => 'Only accepted quotations can be converted to a customer.']);
        }

        return $this->conversionForm($request, $crmQuotation->lead, $crmQuotation);
    }

    public function storeForLead(StoreCrmCustomerConversionRequest $request, LeadRepository $leads, CrmCustomerConversionService $conversion, int $lead): RedirectResponse
    {
        $crmLead = $leads->findForUser($request->user(), $lead);
        $quotation = CrmQuotation::query()
            ->where('company_id', $crmLead->company_id)
            ->where('lead_id', $crmLead->id)
            ->where('status', QuotationStatus::Accepted->value)
            ->latest('accepted_at')
            ->first();
        $customer = $conversion->convert($crmLead, $request->user(), $request->validated(), $quotation);

        return redirect()->route('crm.customers.show', $customer)->with('status', 'Lead converted to CRM customer.');
    }

    public function storeForQuotation(StoreCrmCustomerConversionRequest $request, QuotationRepository $quotations, CrmCustomerConversionService $conversion, int $quotation): RedirectResponse
    {
        $crmQuotation = $quotations->findForUser($request->user(), $quotation);
        $customer = $conversion->convert($crmQuotation->lead, $request->user(), $request->validated(), $crmQuotation);

        return redirect()->route('crm.customers.show', $customer)->with('status', 'Accepted quotation converted to CRM customer.');
    }

    private function conversionForm(Request $request, CrmLead $lead, ?CrmQuotation $quotation = null): View|RedirectResponse
    {
        if ($lead->crmCustomer) {
            return redirect()->route('crm.customers.show', $lead->crmCustomer);
        }

        $quotation ??= $lead->quotations()->where('status', QuotationStatus::Accepted->value)->latest('accepted_at')->first();

        return view('command-center.crm.customers.form', [
            'lead' => $lead,
            'quotation' => $quotation,
            'customer' => new CrmCustomer([
                'company_name' => $quotation?->customer_company ?? $lead->business_name ?? $lead->title,
                'display_name' => $quotation?->customer_name ?? $lead->contact_name ?? $lead->business_name ?? $lead->title,
                'business_type' => $lead->business_type ?? $lead->industry,
                'email' => $quotation?->customer_email ?? $lead->email,
                'phone' => $quotation?->customer_phone ?? $lead->phone,
                'country' => $lead->country,
                'city' => $lead->city,
                'billing_address' => $quotation?->billing_address,
                'status' => CrmCustomerStatus::Onboarding,
            ]),
            'statuses' => CrmCustomerStatus::cases(),
        ]);
    }
}
