<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SaasReseller;
use App\Models\SaasResellerTenantAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Saas\SaasResellerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaasResellerController extends Controller
{
    public function index(Request $request): View
    {
        return view('command-center.saas.resellers.index', [
            'resellers' => SaasReseller::query()->with('owner')->withCount(['tenantAssignments as active_tenants_count' => fn ($query) => $query->whereNull('unassigned_at')])->latest()->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('command-center.saas.resellers.form', ['reseller' => new SaasReseller(['status' => 'active'])]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $reseller = SaasReseller::create($this->validated($request) + ['owner_id' => $request->user()->id]);
        $audit->record('saas.reseller.created', $reseller, 'Reseller created.');

        return redirect()->route('saas.resellers.show', $reseller)->with('status', 'Reseller created.');
    }

    public function show(SaasReseller $reseller): View
    {
        return view('command-center.saas.resellers.show', [
            'reseller' => $reseller->load(['owner', 'tenantAssignments.company', 'tenantAssignments.assignedBy']),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(SaasReseller $reseller): View
    {
        return view('command-center.saas.resellers.form', compact('reseller'));
    }

    public function update(Request $request, SaasReseller $reseller, AuditLogger $audit): RedirectResponse
    {
        $reseller->update($this->validated($request));
        $audit->record('saas.reseller.updated', $reseller, 'Reseller updated.');

        return redirect()->route('saas.resellers.show', $reseller)->with('status', 'Reseller updated.');
    }

    public function assign(Request $request, SaasReseller $reseller, SaasResellerService $resellers): RedirectResponse
    {
        $data = $request->validate(['company_id' => ['required', Rule::exists('companies', 'id')], 'notes' => ['nullable', 'string', 'max:1000']]);
        $resellers->assign($reseller, Company::findOrFail($data['company_id']), $request->user(), $data['notes'] ?? null);

        return back()->with('status', 'Tenant assigned. The assignment is recorded in the reseller history.');
    }

    public function unassign(SaasReseller $reseller, SaasResellerTenantAssignment $assignment, Request $request, SaasResellerService $resellers): RedirectResponse
    {
        abort_unless($assignment->saas_reseller_id === $reseller->id, 404);
        $resellers->unassign($assignment, $request->user());

        return back()->with('status', 'Tenant assignment closed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'partner_code' => ['required', 'string', 'max:80', Rule::unique('saas_resellers', 'partner_code')->ignore($request->route('reseller'))],
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(['active', 'inactive', 'prospect', 'terminated'])],
            'referral_source' => ['nullable', 'string', 'max:100'],
            'agreement_starts_at' => ['nullable', 'date'],
            'agreement_ends_at' => ['nullable', 'date', 'after_or_equal:agreement_starts_at'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
