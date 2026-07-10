<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCrmCompanyRequest;
use App\Http\Requests\Crm\UpdateCrmCompanyRequest;
use App\Models\Crm\CrmCompany;
use App\Models\User;
use App\Repositories\Crm\CrmCompanyRepository;
use App\Repositories\Crm\LeadRepository;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class CrmCompanyController extends Controller
{
    public function index(Request $request, CrmCompanyRepository $companyRepository): View
    {
        return view('command-center.crm.companies.index', [
            'crmCompanies' => $companyRepository->paginateForUser($request->user(), $request->only(['search', 'industry', 'trashed'])),
        ]);
    }

    public function create(Request $request, LeadRepository $leadRepository): View
    {
        return view('command-center.crm.companies.create', [
            'crmCompany' => new CrmCompany(['is_active' => true]),
            'users' => $this->usersForCompany($request->user()->company_id),
            'tags' => $leadRepository->tagsForCompany($request->user()->company_id),
        ]);
    }

    public function store(StoreCrmCompanyRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $crmCompany = CrmCompany::create(Arr::except($request->validated(), ['tag_ids']) + [
            'company_id' => $request->user()->company_id,
            'assigned_user_id' => $request->validated('assigned_user_id') ?? $request->user()->id,
            'is_active' => $request->boolean('is_active', true),
        ]);
        $crmCompany->tags()->sync($request->validated('tag_ids') ?? []);
        $auditLogger->record('crm.company.created', $crmCompany, 'CRM company created');

        return redirect()->route('crm.companies.show', $crmCompany)->with('status', 'CRM company created.');
    }

    public function show(Request $request, CrmCompanyRepository $companyRepository, int $company): View
    {
        return view('command-center.crm.companies.show', [
            'crmCompany' => $companyRepository->findForUser($request->user(), $company),
        ]);
    }

    public function edit(Request $request, CrmCompanyRepository $companyRepository, LeadRepository $leadRepository, int $company): View
    {
        return view('command-center.crm.companies.edit', [
            'crmCompany' => $companyRepository->findForUser($request->user(), $company, true),
            'users' => $this->usersForCompany($request->user()->company_id),
            'tags' => $leadRepository->tagsForCompany($request->user()->company_id),
        ]);
    }

    public function update(UpdateCrmCompanyRequest $request, CrmCompanyRepository $companyRepository, AuditLogger $auditLogger, int $company): RedirectResponse
    {
        $crmCompany = $companyRepository->findForUser($request->user(), $company, true);
        $crmCompany->update(Arr::except($request->validated(), ['tag_ids']) + [
            'is_active' => $request->boolean('is_active', true),
        ]);
        $crmCompany->tags()->sync($request->validated('tag_ids') ?? []);
        $auditLogger->record('crm.company.updated', $crmCompany, 'CRM company updated');

        return back()->with('status', 'CRM company updated.');
    }

    public function destroy(Request $request, CrmCompanyRepository $companyRepository, AuditLogger $auditLogger, int $company): RedirectResponse
    {
        $crmCompany = $companyRepository->findForUser($request->user(), $company);
        $crmCompany->delete();
        $auditLogger->record('crm.company.deleted', $crmCompany, 'CRM company deleted');

        return redirect()->route('crm.companies.index')->with('status', 'CRM company moved to trash.');
    }

    public function restore(Request $request, CrmCompanyRepository $companyRepository, AuditLogger $auditLogger, int $company): RedirectResponse
    {
        $crmCompany = $companyRepository->findForUser($request->user(), $company, true);
        $crmCompany->restore();
        $auditLogger->record('crm.company.restored', $crmCompany, 'CRM company restored');

        return back()->with('status', 'CRM company restored.');
    }

    private function usersForCompany(int $companyId)
    {
        return User::query()->where('company_id', $companyId)->orderBy('name')->get();
    }
}
