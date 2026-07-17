<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\SupportTicketMessageVisibility;
use App\Enums\Crm\SupportTicketPriority;
use App\Enums\Crm\SupportTicketStatus;
use App\Events\Domain\Crm\SupportTicketEvent;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmSupportTicket;
use App\Models\Crm\CrmSupportTicketAttachment;
use App\Models\Crm\CrmSupportTicketMessage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmSupportTicketService
{
    public function __construct(private readonly CrmSupportSlaService $sla, private readonly AuditLogger $auditLogger, private readonly DomainEventDispatcher $events) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): CrmSupportTicket
    {
        return DB::transaction(function () use ($actor, $data): CrmSupportTicket {
            $context = $this->context($actor, $data);
            $priority = SupportTicketPriority::from($data['priority']);
            $deadlines = $this->sla->deadlines($priority->value);
            Company::query()->orderBy('id')->lockForUpdate()->firstOrFail();

            $ticket = CrmSupportTicket::create([
                'company_id' => $actor->company_id,
                'customer_id' => $context['customer']?->id,
                'lead_id' => $context['lead']?->id,
                'onboarding_id' => $context['onboarding']?->id,
                'proforma_invoice_id' => $context['proforma']?->id,
                'ticket_number' => $this->nextNumber(),
                'subject' => $data['subject'],
                'description' => $data['description'],
                'category' => $data['category'],
                'priority' => $priority,
                'status' => SupportTicketStatus::New,
                'source' => $data['source'],
                'assigned_to' => $data['assigned_to'] ?? $context['lead']?->assigned_user_id,
                'reported_by_name' => $data['reported_by_name'] ?? $context['customer']?->primaryContact?->name ?? $context['customer']?->display_name,
                'reported_by_email' => $data['reported_by_email'] ?? $context['customer']?->primaryContact?->email ?? $context['customer']?->email,
                'reported_by_phone' => $data['reported_by_phone'] ?? $context['customer']?->primaryContact?->phone ?? $context['customer']?->phone,
                'first_response_due_at' => $deadlines['first_response_due_at'],
                'due_at' => $data['due_at'] ?? $deadlines['due_at'],
                'internal_remarks' => $data['internal_remarks'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $ticket->statusHistories()->create(['new_status' => SupportTicketStatus::New->value, 'changed_by' => $actor->id, 'note' => 'Ticket created']);
            $this->activity($ticket, $actor, 'Support ticket created: '.$ticket->ticket_number, $ticket->subject);
            $this->auditLogger->record('crm.support.created', $ticket, 'Support ticket created');
            $this->dispatch('crm.support_ticket_created', $ticket, $actor);
            if ($ticket->priority === SupportTicketPriority::Urgent) $this->dispatch('crm.support_ticket_urgent', $ticket, $actor);
            return $ticket->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(CrmSupportTicket $ticket, User $actor, array $data): CrmSupportTicket
    {
        $this->assertAssignedUser($actor, $data['assigned_to'] ?? null);
        $beforeAssigned = $ticket->assigned_to; $beforePriority = $ticket->priority;
        $status = isset($data['status']) ? SupportTicketStatus::from($data['status']) : null;
        if ($status && $status !== $ticket->status) {
            $this->ensureTransition($ticket->status, $status);
            if ($status === SupportTicketStatus::Resolved && blank($data['resolution_summary'] ?? $ticket->resolution_summary)) {
                throw ValidationException::withMessages(['resolution_summary' => 'Add a resolution summary before resolving this ticket.']);
            }
        }
        $fields = ['subject', 'description', 'category', 'priority', 'source', 'assigned_to', 'reported_by_name', 'reported_by_email', 'reported_by_phone', 'due_at', 'internal_remarks'];
        if (! $status || $status === $ticket->status) $fields[] = 'resolution_summary';
        $ticket->update(Arr::only($data, $fields) + ['updated_by' => $actor->id]);
        if ($status && $status !== $ticket->status) $this->setStatus($ticket, $actor, $status, $data['resolution_summary'] ?? null, $data['status_note'] ?? null);
        if ($ticket->assigned_to !== $beforeAssigned) { $this->activity($ticket, $actor, 'Support ticket reassigned.', null); $this->auditLogger->record('crm.support.assigned', $ticket, 'Support ticket assigned'); $this->dispatch('crm.support_ticket_assigned', $ticket, $actor); }
        if ($ticket->priority !== $beforePriority) { $this->activity($ticket, $actor, 'Support ticket priority changed to '.$ticket->priority->label().'.', null); $this->auditLogger->record('crm.support.priority_changed', $ticket, 'Support ticket priority changed'); if ($ticket->priority === SupportTicketPriority::Urgent) $this->dispatch('crm.support_ticket_urgent', $ticket, $actor); }
        $this->auditLogger->record('crm.support.updated', $ticket, 'Support ticket updated');
        return $ticket->refresh();
    }

    public function setStatus(CrmSupportTicket $ticket, User $actor, SupportTicketStatus $status, ?string $resolutionSummary = null, ?string $note = null): CrmSupportTicket
    {
        $this->ensureTransition($ticket->status, $status);
        if ($status === SupportTicketStatus::Resolved && blank($resolutionSummary ?? $ticket->resolution_summary)) throw ValidationException::withMessages(['resolution_summary' => 'Add a resolution summary before resolving this ticket.']);
        $from = $ticket->status;
        $payload = ['status' => $status, 'updated_by' => $actor->id];
        if ($status === SupportTicketStatus::Resolved) { $payload['resolved_at'] = $ticket->resolved_at ?: now(); $payload['resolution_summary'] = $resolutionSummary ?? $ticket->resolution_summary; }
        if ($status === SupportTicketStatus::Closed) $payload['closed_at'] = $ticket->closed_at ?: now();
        if ($status === SupportTicketStatus::Reopened) { $payload['reopened_at'] = now(); $payload['closed_at'] = null; }
        $ticket->update($payload);
        $ticket->statusHistories()->create(['old_status' => $from->value, 'new_status' => $status->value, 'changed_by' => $actor->id, 'note' => $note]);
        $messageType = $status === SupportTicketStatus::Resolved ? 'resolution' : 'status_update';
        $message = $status === SupportTicketStatus::Resolved ? ($payload['resolution_summary'] ?? 'Ticket resolved.') : 'Status changed from '.$from->label().' to '.$status->label().'.';
        $ticket->messages()->create(['message' => $message, 'visibility' => $status === SupportTicketStatus::Resolved ? SupportTicketMessageVisibility::CustomerSafe : SupportTicketMessageVisibility::Internal, 'message_type' => $messageType, 'created_by' => $actor->id]);
        $this->activity($ticket, $actor, 'Support ticket status changed to '.$status->label().'.', $note);
        $event = match ($status) { SupportTicketStatus::Resolved => 'crm.support_ticket_resolved', SupportTicketStatus::Reopened => 'crm.support_ticket_reopened', SupportTicketStatus::WaitingForInternalTeam => 'crm.support_ticket_waiting_internal', default => 'crm.support_ticket_status_changed' };
        $this->auditLogger->record('crm.support.status_changed', $ticket, 'Support ticket status changed');
        $this->dispatch($event, $ticket, $actor);
        return $ticket->refresh();
    }

    /** @param array<string, mixed> $data */
    public function addMessage(CrmSupportTicket $ticket, User $actor, array $data): CrmSupportTicketMessage
    {
        $visibility = SupportTicketMessageVisibility::from($data['visibility']);
        $message = $ticket->messages()->create(['message' => $data['message'], 'visibility' => $visibility, 'message_type' => $visibility === SupportTicketMessageVisibility::CustomerSafe ? 'reply' : 'note', 'created_by' => $actor->id]);
        $this->activity($ticket, $actor, $visibility === SupportTicketMessageVisibility::CustomerSafe ? 'Customer-safe support reply added.' : 'Internal support note added.', null);
        $this->auditLogger->record($visibility === SupportTicketMessageVisibility::CustomerSafe ? 'crm.support.reply_added' : 'crm.support.note_added', $ticket, 'Support ticket message added');
        return $message;
    }

    /** @param array<string, mixed> $data */
    public function addAttachment(CrmSupportTicket $ticket, User $actor, array $data): CrmSupportTicketAttachment
    {
        $attachment = $ticket->attachments()->create(Arr::only($data, ['title', 'external_url', 'mime_type', 'file_size', 'message_id']) + ['uploaded_by' => $actor->id]);
        $this->activity($ticket, $actor, 'Support ticket link added: '.$attachment->title.'.', null);
        $this->auditLogger->record('crm.support.attachment_added', $attachment, 'Support ticket attachment link added');
        return $attachment;
    }

    /** @param array<string, mixed> $data @return array{customer: ?CrmCustomer, lead: ?CrmLead, onboarding: ?CrmCustomerOnboarding, proforma: ?CrmProformaInvoice} */
    private function context(User $actor, array $data): array
    {
        $customer = isset($data['customer_id']) ? CrmCustomer::query()->where('company_id', $actor->company_id)->with(['primaryContact', 'lead'])->find($data['customer_id']) : null;
        $lead = isset($data['lead_id']) ? CrmLead::query()->where('company_id', $actor->company_id)->find($data['lead_id']) : null;
        $onboarding = isset($data['onboarding_id']) ? CrmCustomerOnboarding::query()->where('company_id', $actor->company_id)->find($data['onboarding_id']) : null;
        $proforma = isset($data['proforma_invoice_id']) ? CrmProformaInvoice::query()->where('company_id', $actor->company_id)->find($data['proforma_invoice_id']) : null;
        if ((isset($data['customer_id']) && ! $customer) || (isset($data['lead_id']) && ! $lead) || (isset($data['onboarding_id']) && ! $onboarding) || (isset($data['proforma_invoice_id']) && ! $proforma)) throw ValidationException::withMessages(['customer_id' => 'Linked support records must belong to this company.']);
        $customer ??= $onboarding?->customer; $lead ??= $customer?->lead ?? $onboarding?->lead ?? $proforma?->lead; $customer ??= $proforma?->customer;
        if (! $customer && blank($data['reported_by_name'] ?? null) && blank($data['reported_by_email'] ?? null) && blank($data['reported_by_phone'] ?? null)) throw ValidationException::withMessages(['reported_by_name' => 'Choose a customer or provide at least one reporter contact detail.']);
        $this->assertAssignedUser($actor, $data['assigned_to'] ?? null);
        return compact('customer', 'lead', 'onboarding', 'proforma');
    }

    private function nextNumber(): string { $prefix = 'TKT-'.now()->format('Y').'-'; $last = CrmSupportTicket::query()->where('ticket_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('ticket_number'); return $prefix.str_pad((string) (($last ? (int) str($last)->afterLast('-')->toString() : 0) + 1), 6, '0', STR_PAD_LEFT); }
    private function ensureTransition(SupportTicketStatus $from, SupportTicketStatus $to): void { $allowed = ['new' => ['open'], 'open' => ['in_progress'], 'in_progress' => ['waiting_for_customer', 'waiting_for_internal_team', 'resolved'], 'waiting_for_customer' => ['in_progress', 'resolved'], 'waiting_for_internal_team' => ['in_progress', 'resolved'], 'resolved' => ['closed', 'reopened'], 'closed' => ['reopened'], 'reopened' => ['in_progress']]; if ($from !== $to && ! in_array($to->value, $allowed[$from->value], true)) throw ValidationException::withMessages(['status' => 'This ticket status transition is not allowed.']); }
    private function assertAssignedUser(User $actor, mixed $id): void { if ($id && ! User::query()->where('company_id', $actor->company_id)->where('is_active', true)->whereKey($id)->exists()) throw ValidationException::withMessages(['assigned_to' => 'Assigned staff must be an active user in this company.']); }
    private function activity(CrmSupportTicket $ticket, User $actor, string $subject, ?string $description): void { if (! $ticket->lead_id) return; \App\Models\Crm\CrmActivity::create(['company_id' => $ticket->company_id, 'crm_lead_id' => $ticket->lead_id, 'assigned_user_id' => $ticket->assigned_to, 'created_by' => $actor->id, 'type' => ActivityType::Task, 'subject' => $subject, 'description' => $description, 'completed_at' => now(), 'priority' => LeadPriority::Medium]); }
    private function dispatch(string $event, CrmSupportTicket $ticket, ?User $actor): void { $this->events->dispatch(new SupportTicketEvent($event, $ticket->company_id, $actor?->id, CrmSupportTicket::class, $ticket->id, ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'ticket_subject' => $ticket->subject, 'customer_name' => $ticket->customer?->company_name ?: $ticket->reported_by_name, 'assigned_user_id' => $ticket->assigned_to, 'priority' => $ticket->priority->value, 'status' => $ticket->status->value, 'due_at' => $ticket->due_at?->toIso8601String()], $event.':'.$ticket->id.':'.($event === 'crm.support_ticket_overdue' ? now()->toDateString() : (string) now()->timestamp))); }
}
