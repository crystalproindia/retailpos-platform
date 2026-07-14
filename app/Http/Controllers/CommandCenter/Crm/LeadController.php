<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\LeadPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\BulkLeadActionRequest;
use App\Http\Requests\Crm\ConvertLeadRequest;
use App\Http\Requests\Crm\StoreLeadRequest;
use App\Http\Requests\Crm\StoreNoteRequest;
use App\Http\Requests\Crm\UpdateLeadRequest;
use App\Models\Crm\CrmLead;
use App\Models\User;
use App\Repositories\Crm\ContactRepository;
use App\Repositories\Crm\CrmCompanyRepository;
use App\Repositories\Crm\LeadRepository;
use App\Services\Crm\LeadConversionService;
use App\Services\Crm\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request, LeadRepository $leadRepository): View
    {
        return view('command-center.crm.leads.index', [
            'leads' => $leadRepository->paginateForUser($request->user(), $request->only(['search', 'status_id', 'source_id', 'priority', 'assigned_user_id', 'demo_requests', 'trashed'])),
            'statuses' => $leadRepository->statusesForCompany($request->user()->company_id),
            'sources' => $leadRepository->sourcesForCompany($request->user()->company_id),
            'tags' => $leadRepository->tagsForCompany($request->user()->company_id),
            'users' => $this->usersForCompany($request->user()->company_id),
            'priorities' => LeadPriority::cases(),
        ]);
    }

    public function demoRequests(Request $request, LeadRepository $leadRepository): View
    {
        $request->merge(['demo_requests' => true]);

        return $this->index($request, $leadRepository);
    }

    public function create(Request $request, LeadRepository $leadRepository, CrmCompanyRepository $companyRepository, ContactRepository $contactRepository): View
    {
        $status = $leadRepository->statusesForCompany($request->user()->company_id)->first();

        return view('command-center.crm.leads.create', [
            'lead' => new CrmLead([
                'status_id' => $status?->id,
                'priority' => LeadPriority::Medium,
                'currency' => 'INR',
            ]),
            'statuses' => $leadRepository->statusesForCompany($request->user()->company_id),
            'sources' => $leadRepository->sourcesForCompany($request->user()->company_id),
            'tags' => $leadRepository->tagsForCompany($request->user()->company_id),
            'users' => $this->usersForCompany($request->user()->company_id),
            'crmCompanies' => $companyRepository->optionsForUser($request->user()),
            'contacts' => $contactRepository->optionsForUser($request->user()),
            'priorities' => LeadPriority::cases(),
        ]);
    }

    public function store(StoreLeadRequest $request, LeadService $leadService): RedirectResponse
    {
        $lead = $leadService->create($request->user(), $request->validated());

        return redirect()->route('crm.leads.show', $lead)->with('status', 'CRM lead created.');
    }

    public function show(Request $request, LeadRepository $leadRepository, int $lead): View
    {
        return view('command-center.crm.leads.show', [
            'lead' => $leadRepository->findForUser($request->user(), $lead),
            'priorities' => LeadPriority::cases(),
        ]);
    }

    public function edit(Request $request, LeadRepository $leadRepository, CrmCompanyRepository $companyRepository, ContactRepository $contactRepository, int $lead): View
    {
        return view('command-center.crm.leads.edit', [
            'lead' => $leadRepository->findForUser($request->user(), $lead, true),
            'statuses' => $leadRepository->statusesForCompany($request->user()->company_id),
            'sources' => $leadRepository->sourcesForCompany($request->user()->company_id),
            'tags' => $leadRepository->tagsForCompany($request->user()->company_id),
            'users' => $this->usersForCompany($request->user()->company_id),
            'crmCompanies' => $companyRepository->optionsForUser($request->user()),
            'contacts' => $contactRepository->optionsForUser($request->user()),
            'priorities' => LeadPriority::cases(),
        ]);
    }

    public function update(UpdateLeadRequest $request, LeadRepository $leadRepository, LeadService $leadService, int $lead): RedirectResponse
    {
        $leadService->update($leadRepository->findForUser($request->user(), $lead), $request->user(), $request->validated());

        return back()->with('status', 'CRM lead updated.');
    }

    public function destroy(Request $request, LeadRepository $leadRepository, LeadService $leadService, int $lead): RedirectResponse
    {
        Gate::authorize('crm.leads.delete');

        $leadService->delete($leadRepository->findForUser($request->user(), $lead));

        return redirect()->route('crm.leads.index')->with('status', 'CRM lead moved to trash.');
    }

    public function restore(Request $request, LeadRepository $leadRepository, LeadService $leadService, int $lead): RedirectResponse
    {
        Gate::authorize('crm.leads.delete');

        $leadService->restore($leadRepository->findForUser($request->user(), $lead, true));

        return back()->with('status', 'CRM lead restored.');
    }

    public function bulk(BulkLeadActionRequest $request, LeadService $leadService): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['action'] === 'assign') {
            Gate::authorize('crm.leads.assign');
            $leadService->bulkAssign($request->user(), $validated['ids'], (int) $validated['assigned_user_id']);
        } else {
            $leadService->bulkStatus($request->user(), $validated['ids'], (int) $validated['status_id']);
        }

        return back()->with('status', 'CRM bulk action completed.');
    }

    public function note(StoreNoteRequest $request, LeadRepository $leadRepository, LeadService $leadService, int $lead): RedirectResponse
    {
        $leadService->addNote(
            $leadRepository->findForUser($request->user(), $lead),
            $request->user(),
            $request->validated('body'),
        );

        return back()->with('status', 'CRM note added.');
    }

    public function convert(ConvertLeadRequest $request, LeadRepository $leadRepository, LeadConversionService $conversionService, int $lead): RedirectResponse
    {
        $converted = $conversionService->convert(
            $leadRepository->findForUser($request->user(), $lead),
            $request->user(),
            $request->validated(),
        );

        return redirect()->route('crm.leads.show', $converted)->with('status', 'CRM lead converted.');
    }

    private function usersForCompany(int $companyId)
    {
        return User::query()->where('company_id', $companyId)->orderBy('name')->get();
    }
}
