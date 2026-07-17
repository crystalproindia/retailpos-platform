<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\OnboardingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StartCrmOnboardingRequest;
use App\Http\Requests\Crm\StoreCrmOnboardingDocumentRequest;
use App\Http\Requests\Crm\StoreCrmOnboardingNoteRequest;
use App\Http\Requests\Crm\StoreCrmOnboardingTaskRequest;
use App\Http\Requests\Crm\UpdateCrmOnboardingRequest;
use App\Http\Requests\Crm\UpdateCrmOnboardingTaskRequest;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmOnboardingDocument;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\User;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Repositories\Crm\CrmOnboardingRepository;
use App\Repositories\Crm\ProformaRepository;
use App\Services\Crm\CrmOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrmOnboardingController extends Controller
{
    public function index(Request $request, CrmOnboardingRepository $onboardings): View { return view('command-center.crm.onboarding.index', ['onboardings' => $onboardings->paginate($request->user(), $request->only(['search', 'status', 'priority', 'owner_id', 'overdue', 'target_from', 'target_to'])), 'owners' => User::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])]); }
    public function show(Request $request, CrmOnboardingRepository $onboardings, int $onboarding): View { return view('command-center.crm.onboarding.show', ['onboarding' => $onboardings->find($request->user(), $onboarding), 'owners' => User::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])]); }
    public function edit(Request $request, CrmOnboardingRepository $onboardings, int $onboarding): View { return view('command-center.crm.onboarding.edit', ['onboarding' => $onboardings->find($request->user(), $onboarding), 'owners' => User::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])]); }
    public function update(UpdateCrmOnboardingRequest $request, CrmOnboardingRepository $onboardings, CrmOnboardingService $service, int $onboarding): RedirectResponse { $record = $onboardings->find($request->user(), $onboarding); $data = $request->validated(); if (($data['assigned_to'] ?? null) != $record->assigned_to || ($data['implementation_owner_id'] ?? null) != $record->implementation_owner_id) { abort_unless($request->user()->can('crm.onboarding.assign'), 403); } $service->update($record, $request->user(), $data); return redirect()->route('crm.onboarding.show', $onboarding)->with('status', 'Onboarding updated.'); }
    public function startFromCustomer(StartCrmOnboardingRequest $request, CrmCustomerRepository $customers, CrmOnboardingService $service, int $customer): RedirectResponse { $onboarding = $service->start($customers->findForUser($request->user(), $customer), $request->user(), $request->validated()); return redirect()->route('crm.onboarding.show', $onboarding)->with('status', 'Customer onboarding started.'); }
    public function startFromProforma(StartCrmOnboardingRequest $request, ProformaRepository $proformas, CrmOnboardingService $service, int $proforma): RedirectResponse { $invoice = $proformas->find($request->user(), $proforma); if (! $invoice->customer) { throw ValidationException::withMessages(['proforma' => 'Convert or link this proforma to a CRM customer before starting onboarding.']); } $onboarding = $service->start($invoice->customer, $request->user(), $request->validated(), $invoice); return redirect()->route('crm.onboarding.show', $onboarding)->with('status', 'Customer onboarding started from proforma.'); }
    public function task(UpdateCrmOnboardingTaskRequest $request, CrmOnboardingRepository $onboardings, CrmOnboardingService $service, int $onboarding, int $task): RedirectResponse { $record = $onboardings->find($request->user(), $onboarding); $service->updateTask($record, $onboardings->task($record, $task), $request->user(), $request->validated()); return back()->with('status', 'Task updated.'); }
    public function storeTask(StoreCrmOnboardingTaskRequest $request, CrmOnboardingRepository $onboardings, CrmOnboardingService $service, int $onboarding): RedirectResponse { $service->addTask($onboardings->find($request->user(), $onboarding), $request->user(), $request->validated()); return back()->with('status', 'Task added.'); }
    public function note(StoreCrmOnboardingNoteRequest $request, CrmOnboardingRepository $onboardings, CrmOnboardingService $service, int $onboarding): RedirectResponse { $service->addNote($onboardings->find($request->user(), $onboarding), $request->user(), $request->validated()); return back()->with('status', 'Note added.'); }
    public function document(StoreCrmOnboardingDocumentRequest $request, CrmOnboardingRepository $onboardings, CrmOnboardingService $service, int $onboarding): RedirectResponse { $service->addDocument($onboardings->find($request->user(), $onboarding), $request->user(), $request->validated()); return back()->with('status', 'Document request added.'); }
    public function updateDocument(StoreCrmOnboardingDocumentRequest $request, CrmOnboardingRepository $onboardings, CrmOnboardingService $service, int $onboarding, int $document): RedirectResponse { $record = $onboardings->find($request->user(), $onboarding); $service->updateDocument($record, $record->documents()->findOrFail($document), $request->user(), $request->validated()); return back()->with('status', 'Document updated.'); }
    public function status(Request $request, CrmOnboardingRepository $onboardings, CrmOnboardingService $service, int $onboarding): RedirectResponse { $request->validate(['status' => ['required', Rule::enum(OnboardingStatus::class)]]); $status = OnboardingStatus::from($request->string('status')->value()); if ($status === OnboardingStatus::Cancelled && ! $request->user()->can('crm.onboarding.cancel')) { abort(403); } $service->setStatus($onboardings->find($request->user(), $onboarding), $request->user(), $status); return back()->with('status', 'Onboarding status updated.'); }
}
