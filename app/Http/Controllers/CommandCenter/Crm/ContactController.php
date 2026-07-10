<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\PreferredContactMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCrmContactRequest;
use App\Http\Requests\Crm\UpdateCrmContactRequest;
use App\Models\Crm\CrmContact;
use App\Models\User;
use App\Repositories\Crm\ContactRepository;
use App\Repositories\Crm\CrmCompanyRepository;
use App\Repositories\Crm\LeadRepository;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request, ContactRepository $contactRepository, CrmCompanyRepository $companyRepository): View
    {
        return view('command-center.crm.contacts.index', [
            'contacts' => $contactRepository->paginateForUser($request->user(), $request->only(['search', 'crm_company_id', 'trashed'])),
            'crmCompanies' => $companyRepository->optionsForUser($request->user()),
        ]);
    }

    public function create(Request $request, CrmCompanyRepository $companyRepository, LeadRepository $leadRepository): View
    {
        return view('command-center.crm.contacts.create', [
            'contact' => new CrmContact(['preferred_contact_method' => PreferredContactMethod::Phone]),
            'crmCompanies' => $companyRepository->optionsForUser($request->user()),
            'users' => $this->usersForCompany($request->user()->company_id),
            'tags' => $leadRepository->tagsForCompany($request->user()->company_id),
            'methods' => PreferredContactMethod::cases(),
        ]);
    }

    public function store(StoreCrmContactRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $contact = CrmContact::create(Arr::except($request->validated(), ['tag_ids']) + [
            'company_id' => $request->user()->company_id,
            'assigned_user_id' => $request->validated('assigned_user_id') ?? $request->user()->id,
            'is_primary' => $request->boolean('is_primary'),
        ]);
        $contact->tags()->sync($request->validated('tag_ids') ?? []);
        $auditLogger->record('crm.contact.created', $contact, 'CRM contact created');

        return redirect()->route('crm.contacts.show', $contact)->with('status', 'CRM contact created.');
    }

    public function show(Request $request, ContactRepository $contactRepository, int $contact): View
    {
        return view('command-center.crm.contacts.show', [
            'contact' => $contactRepository->findForUser($request->user(), $contact),
        ]);
    }

    public function edit(Request $request, ContactRepository $contactRepository, CrmCompanyRepository $companyRepository, LeadRepository $leadRepository, int $contact): View
    {
        return view('command-center.crm.contacts.edit', [
            'contact' => $contactRepository->findForUser($request->user(), $contact, true),
            'crmCompanies' => $companyRepository->optionsForUser($request->user()),
            'users' => $this->usersForCompany($request->user()->company_id),
            'tags' => $leadRepository->tagsForCompany($request->user()->company_id),
            'methods' => PreferredContactMethod::cases(),
        ]);
    }

    public function update(UpdateCrmContactRequest $request, ContactRepository $contactRepository, AuditLogger $auditLogger, int $contact): RedirectResponse
    {
        $crmContact = $contactRepository->findForUser($request->user(), $contact, true);
        $crmContact->update(Arr::except($request->validated(), ['tag_ids']) + [
            'is_primary' => $request->boolean('is_primary'),
        ]);
        $crmContact->tags()->sync($request->validated('tag_ids') ?? []);
        $auditLogger->record('crm.contact.updated', $crmContact, 'CRM contact updated');

        return back()->with('status', 'CRM contact updated.');
    }

    public function destroy(Request $request, ContactRepository $contactRepository, AuditLogger $auditLogger, int $contact): RedirectResponse
    {
        $crmContact = $contactRepository->findForUser($request->user(), $contact);
        $crmContact->delete();
        $auditLogger->record('crm.contact.deleted', $crmContact, 'CRM contact deleted');

        return redirect()->route('crm.contacts.index')->with('status', 'CRM contact moved to trash.');
    }

    public function restore(Request $request, ContactRepository $contactRepository, AuditLogger $auditLogger, int $contact): RedirectResponse
    {
        $crmContact = $contactRepository->findForUser($request->user(), $contact, true);
        $crmContact->restore();
        $auditLogger->record('crm.contact.restored', $crmContact, 'CRM contact restored');

        return back()->with('status', 'CRM contact restored.');
    }

    private function usersForCompany(int $companyId)
    {
        return User::query()->where('company_id', $companyId)->orderBy('name')->get();
    }
}
