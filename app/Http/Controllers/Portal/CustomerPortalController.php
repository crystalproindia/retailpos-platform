<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmCustomerPortalUser;
use App\Repositories\Portal\CustomerPortalRepository;
use App\Rules\SafeCmsUrl;
use App\Services\Portal\CustomerPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerPortalController extends Controller
{
    public function dashboard(Request $request, CustomerPortalRepository $portal): View
    {
        return view('portal.dashboard', ['portalUser' => $this->portalUser($request), 'metrics' => $portal->dashboard($this->portalUser($request))]);
    }

    public function quotations(Request $request, CustomerPortalRepository $portal): View
    {
        return view('portal.quotations.index', ['portalUser' => $this->portalUser($request), 'quotations' => $portal->quotations($this->portalUser($request)->customer)->latest('created_at')->paginate(12)]);
    }

    public function quotation(Request $request, CustomerPortalRepository $portal, int $quotation): View
    {
        return view('portal.quotations.show', ['portalUser' => $this->portalUser($request), 'quotation' => $portal->quotation($this->portalUser($request)->customer, $quotation)]);
    }

    public function proformas(Request $request, CustomerPortalRepository $portal): View
    {
        return view('portal.proformas.index', ['portalUser' => $this->portalUser($request), 'proformas' => $portal->proformas($this->portalUser($request)->customer)->latest('created_at')->paginate(12)]);
    }

    public function proforma(Request $request, CustomerPortalRepository $portal, int $proforma): View
    {
        return view('portal.proformas.show', ['portalUser' => $this->portalUser($request), 'proforma' => $portal->proforma($this->portalUser($request)->customer, $proforma)]);
    }

    public function onboardings(Request $request, CustomerPortalRepository $portal): View
    {
        return view('portal.onboarding.index', ['portalUser' => $this->portalUser($request), 'onboardings' => $portal->onboardings($this->portalUser($request)->customer)->latest('created_at')->get()]);
    }

    public function onboarding(Request $request, CustomerPortalRepository $portal, int $onboarding): View
    {
        return view('portal.onboarding.show', ['portalUser' => $this->portalUser($request), 'onboarding' => $portal->onboarding($this->portalUser($request)->customer, $onboarding)]);
    }

    public function support(Request $request, CustomerPortalRepository $portal): View
    {
        return view('portal.support.index', ['portalUser' => $this->portalUser($request), 'tickets' => $portal->tickets($this->portalUser($request)->customer)->latest('updated_at')->paginate(12)]);
    }

    public function createSupport(Request $request): View
    {
        return view('portal.support.create', ['portalUser' => $this->portalUser($request)]);
    }

    public function storeSupport(Request $request, CustomerPortalService $portal): RedirectResponse
    {
        $data = $request->validate(['subject' => ['required', 'string', 'max:255'], 'category' => ['required', 'in:general,billing,training,setup,bug,feature_request,data_import,hardware,integration,account_access,other'], 'priority' => ['required', 'in:low,normal,high,urgent'], 'description' => ['required', 'string', 'max:10000'], 'contact_phone' => ['nullable', 'string', 'max:80'], 'external_url' => ['nullable', 'max:1000', new SafeCmsUrl]]);
        $ticket = $portal->createTicket($this->portalUser($request), $data);
        if ($data['external_url'] ?? null) $ticket->attachments()->create(['title' => 'Customer-provided link', 'external_url' => $data['external_url']]);

        return redirect()->route('portal.support.show', $ticket)->with('status', 'Your support request has been submitted. Our team will be in touch shortly.');
    }

    public function showSupport(Request $request, CustomerPortalRepository $portal, int $ticket): View
    {
        return view('portal.support.show', ['portalUser' => $this->portalUser($request), 'ticket' => $portal->ticket($this->portalUser($request)->customer, $ticket)]);
    }

    public function replySupport(Request $request, CustomerPortalRepository $repository, CustomerPortalService $portal, int $ticket): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);
        $ticket = $repository->ticket($this->portalUser($request)->customer, $ticket);
        $portal->reply($this->portalUser($request), $ticket, $data['message']);

        return back()->with('status', 'Your reply has been shared with our support team.');
    }

    public function services(Request $request, CustomerPortalRepository $portal): View
    {
        return view('portal.services.index', ['portalUser' => $this->portalUser($request), 'requests' => $portal->serviceRequests($this->portalUser($request)->customer)]);
    }

    public function createServiceRequest(Request $request): View
    {
        return view('portal.services.request', ['portalUser' => $this->portalUser($request)]);
    }

    public function storeServiceRequest(Request $request, CustomerPortalService $portal): RedirectResponse
    {
        $data = $request->validate(['service_category' => ['required', 'in:Digital Marketing,Mobile App Development,Custom Software Development,ERP,CRM,E-commerce Website / App,Website Development,WhatsApp / SMS / RCS,POS / Retail Expansion,Other'], 'requirement_summary' => ['required', 'string', 'max:10000'], 'preferred_contact_method' => ['required', 'in:phone,whatsapp,email'], 'preferred_callback_at' => ['nullable', 'date', 'after:now'], 'budget_range' => ['nullable', 'string', 'max:100'], 'urgency' => ['required', 'in:low,normal,high'], 'additional_notes' => ['nullable', 'string', 'max:5000']]);
        $portal->requestService($this->portalUser($request), $data);

        return redirect()->route('portal.services')->with('status', 'Your request has been submitted. Our team will contact you shortly.');
    }

    public function profile(Request $request): View
    {
        return view('portal.profile', ['portalUser' => $this->portalUser($request)]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:80']]);
        $this->portalUser($request)->update($data);

        return back()->with('status', 'Your contact details have been updated.');
    }

    private function portalUser(Request $request): CrmCustomerPortalUser
    {
        return $request->attributes->get('portalUser');
    }
}
