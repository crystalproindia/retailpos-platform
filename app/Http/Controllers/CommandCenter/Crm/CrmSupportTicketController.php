<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\SupportTicketCategory;
use App\Enums\Crm\SupportTicketPriority;
use App\Enums\Crm\SupportTicketSource;
use App\Enums\Crm\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCrmSupportTicketAttachmentRequest;
use App\Http\Requests\Crm\StoreCrmSupportTicketMessageRequest;
use App\Http\Requests\Crm\StoreCrmSupportTicketRequest;
use App\Http\Requests\Crm\UpdateCrmSupportTicketRequest;
use App\Repositories\Crm\CrmOnboardingRepository;
use App\Repositories\Crm\CrmSupportTicketRepository;
use App\Repositories\Crm\ProformaRepository;
use App\Services\Crm\CrmSupportSlaService;
use App\Services\Crm\CrmSupportTicketService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmSupportTicketController extends Controller
{
    public function index(Request $request, CrmSupportTicketRepository $tickets): View { return view('command-center.crm.support.index', ['tickets' => $tickets->paginate($request->user(), $request->only(['search', 'status', 'priority', 'category', 'assigned_to', 'source', 'overdue', 'unresolved', 'created_from', 'created_to', 'sort'])), 'metrics' => $tickets->dashboard($request->user()), 'owners' => $this->owners($request)]); }
    public function create(Request $request, CrmSupportTicketRepository $tickets, CrmOnboardingRepository $onboardings, ProformaRepository $proformas): View { $customer = $request->integer('customer') ? $tickets->customersForUser($request->user())->firstWhere('id', $request->integer('customer')) : null; $onboarding = $request->integer('onboarding') ? $onboardings->find($request->user(), $request->integer('onboarding')) : null; $proforma = $request->integer('proforma') ? $proformas->find($request->user(), $request->integer('proforma')) : null; return view('command-center.crm.support.create', ['customers' => $tickets->customersForUser($request->user()), 'owners' => $this->owners($request), 'onboarding' => $onboarding, 'proforma' => $proforma, 'ticket' => ['customer_id' => $customer?->id ?? $onboarding?->customer_id ?? $proforma?->customer_id, 'lead_id' => $customer?->lead_id ?? $onboarding?->lead_id ?? $proforma?->lead_id, 'onboarding_id' => $onboarding?->id, 'proforma_invoice_id' => $proforma?->id, 'reported_by_name' => $customer?->display_name ?? $onboarding?->customer_contact_name ?? $proforma?->customer_name, 'reported_by_email' => $customer?->email ?? $onboarding?->customer_contact_email ?? $proforma?->customer_email, 'reported_by_phone' => $customer?->phone ?? $onboarding?->customer_contact_phone ?? $proforma?->customer_phone]]); }
    public function store(StoreCrmSupportTicketRequest $request, CrmSupportTicketService $service): RedirectResponse { $ticket = $service->create($request->user(), $request->validated()); return redirect()->route('crm.support.tickets.show', $ticket)->with('status', 'Support ticket created and SLA deadlines applied.'); }
    public function show(Request $request, CrmSupportTicketRepository $tickets, CrmSupportSlaService $sla, int $ticket): View { $record = $tickets->find($request->user(), $ticket); return view('command-center.crm.support.show', ['ticket' => $record, 'owners' => $this->owners($request), 'sla' => ['overdue' => $sla->isOverdue($record), 'first_response_overdue' => $sla->isFirstResponseOverdue($record), 'at_risk' => $sla->isAtRisk($record)]]); }
    public function update(UpdateCrmSupportTicketRequest $request, CrmSupportTicketRepository $tickets, CrmSupportTicketService $service, int $ticket): RedirectResponse { $record = $tickets->find($request->user(), $ticket); $data = $request->validated(); if (array_key_exists('assigned_to', $data)) abort_unless($request->user()->can('crm.support.assign'), 403); if (($data['status'] ?? null) === SupportTicketStatus::Resolved->value) abort_unless($request->user()->can('crm.support.resolve'), 403); if (($data['status'] ?? null) === SupportTicketStatus::Closed->value) abort_unless($request->user()->can('crm.support.close'), 403); $service->update($record, $request->user(), $data); return back()->with('status', 'Support ticket updated.'); }
    public function message(StoreCrmSupportTicketMessageRequest $request, CrmSupportTicketRepository $tickets, CrmSupportTicketService $service, int $ticket): RedirectResponse { $service->addMessage($tickets->find($request->user(), $ticket), $request->user(), $request->validated()); return back()->with('status', 'Ticket message added.'); }
    public function attachment(StoreCrmSupportTicketAttachmentRequest $request, CrmSupportTicketRepository $tickets, CrmSupportTicketService $service, int $ticket): RedirectResponse { $service->addAttachment($tickets->find($request->user(), $ticket), $request->user(), $request->validated()); return back()->with('status', 'External attachment link added.'); }
    /** @return \Illuminate\Support\Collection<int, User> */ private function owners(Request $request) { return User::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name']); }
}
