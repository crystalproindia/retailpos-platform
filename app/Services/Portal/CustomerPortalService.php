<?php

namespace App\Services\Portal;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\SupportTicketMessageVisibility;
use App\Enums\Crm\SupportTicketStatus;
use App\Events\Domain\Crm\LeadCreated;
use App\Events\Domain\Crm\SupportTicketEvent;
use App\Models\Company;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmCustomerPortalUser;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmSupportTicket;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Crm\CrmSupportSlaService;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Facades\DB;

class CustomerPortalService
{
    public function __construct(
        private readonly CrmSupportSlaService $sla,
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $events,
    ) {}

    /** @param array<string, mixed> $data */
    public function createTicket(CrmCustomerPortalUser $portalUser, array $data): CrmSupportTicket
    {
        return DB::transaction(function () use ($portalUser, $data): CrmSupportTicket {
            $customer = $portalUser->customer;
            $priority = \App\Enums\Crm\SupportTicketPriority::from($data['priority']);
            $deadlines = $this->sla->deadlines($priority->value);
            Company::query()->orderBy('id')->lockForUpdate()->firstOrFail();
            $ticket = CrmSupportTicket::create([
                'company_id' => $customer->company_id, 'customer_id' => $customer->id, 'customer_portal_user_id' => $portalUser->id,
                'lead_id' => $customer->lead_id, 'ticket_number' => $this->nextTicketNumber(), 'subject' => $data['subject'], 'description' => $data['description'],
                'category' => $data['category'], 'priority' => $priority, 'status' => SupportTicketStatus::New, 'source' => 'customer_portal',
                'reported_by_name' => $portalUser->name, 'reported_by_email' => $portalUser->email, 'reported_by_phone' => $data['contact_phone'] ?? $portalUser->phone ?? $customer->phone,
                'first_response_due_at' => $deadlines['first_response_due_at'], 'due_at' => $deadlines['due_at'],
            ]);
            $ticket->messages()->create(['message' => $data['description'], 'visibility' => SupportTicketMessageVisibility::CustomerSafe, 'message_type' => 'customer_request', 'customer_portal_user_id' => $portalUser->id]);
            $ticket->statusHistories()->create(['new_status' => SupportTicketStatus::New->value, 'note' => 'Ticket created from customer portal']);
            $this->auditLogger->record('crm.support.portal_created', $ticket, 'Customer portal support ticket created');
            $this->dispatchSupport('crm.support_ticket_created', $ticket);
            if ($priority->value === 'urgent') $this->dispatchSupport('crm.support_ticket_urgent', $ticket);

            return $ticket->refresh();
        });
    }

    public function reply(CrmCustomerPortalUser $portalUser, CrmSupportTicket $ticket, string $message): void
    {
        DB::transaction(function () use ($portalUser, $ticket, $message): void {
            $ticket->messages()->create(['message' => $message, 'visibility' => SupportTicketMessageVisibility::CustomerSafe, 'message_type' => 'reply', 'customer_portal_user_id' => $portalUser->id]);
            $from = $ticket->status;
            $to = in_array($from, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true) ? SupportTicketStatus::Reopened : SupportTicketStatus::Open;
            $ticket->update(['status' => $to, 'reopened_at' => $to === SupportTicketStatus::Reopened ? now() : $ticket->reopened_at]);
            if ($from !== $to) $ticket->statusHistories()->create(['old_status' => $from->value, 'new_status' => $to->value, 'note' => 'Customer replied from portal']);
            $this->auditLogger->record('crm.support.portal_replied', $ticket, 'Customer portal support reply added');
            $this->dispatchSupport('crm.support_ticket_status_changed', $ticket);
        });
    }

    /** @param array<string, mixed> $data */
    public function requestService(CrmCustomerPortalUser $portalUser, array $data): CrmLead
    {
        return DB::transaction(function () use ($portalUser, $data): CrmLead {
            $customer = $portalUser->customer;
            $source = CrmLeadSource::query()->firstOrCreate(['company_id' => $customer->company_id, 'slug' => 'customer-portal'], ['name' => 'Customer Portal', 'description' => 'Existing customer service request from the customer portal.', 'tone' => 'info', 'is_active' => true, 'sort_order' => 90]);
            $status = CrmLeadStatus::query()->where('company_id', $customer->company_id)->where('slug', 'new')->firstOrFail();
            $assignee = User::query()->where('company_id', $customer->company_id)->where('is_active', true)->whereIn('role', ['administrator', 'manager', 'sales'])->orderByRaw("CASE role WHEN 'sales' THEN 0 ELSE 1 END")->first();
            $priority = match ($data['urgency']) { 'high' => LeadPriority::High, 'low' => LeadPriority::Low, default => LeadPriority::Medium };
            $lead = CrmLead::create([
                'company_id' => $customer->company_id, 'branch_id' => $customer->lead?->branch_id, 'customer_id' => $customer->id, 'source_id' => $source->id, 'status_id' => $status->id,
                'assigned_user_id' => $assignee?->id, 'title' => $data['service_category'].' service request', 'business_name' => $customer->company_name, 'contact_name' => $portalUser->name,
                'email' => $portalUser->email, 'phone' => $portalUser->phone ?? $customer->phone, 'business_type' => $customer->business_type, 'city' => $customer->city,
                'country' => $customer->country, 'interested_modules' => [$data['service_category']], 'priority' => $priority, 'next_follow_up_at' => $data['preferred_callback_at'] ?? null,
                'description' => $data['requirement_summary'], 'metadata' => ['service_category' => $data['service_category'], 'preferred_contact_method' => $data['preferred_contact_method'], 'urgency' => $data['urgency'], 'budget_range' => $data['budget_range'] ?? null, 'additional_notes' => $data['additional_notes'] ?? null, 'portal_user_id' => $portalUser->id],
            ]);
            CrmActivity::create(['company_id' => $customer->company_id, 'crm_lead_id' => $lead->id, 'assigned_user_id' => $assignee?->id, 'type' => ActivityType::Note, 'subject' => 'Customer requested new service from portal: '.$data['service_category'].'.', 'description' => $data['requirement_summary'], 'scheduled_at' => now(), 'completed_at' => now(), 'priority' => $priority]);
            $this->auditLogger->record('crm.lead.customer_portal_requested', $lead, 'Existing customer requested a new service from portal');
            $this->events->dispatch(new LeadCreated($lead->company_id, null, CrmLead::class, $lead->id, ['lead_id' => $lead->id, 'lead_title' => $lead->title, 'business_name' => $lead->business_name, 'contact_name' => $lead->contact_name, 'email' => $lead->email, 'phone' => $lead->phone, 'assigned_user_id' => $lead->assigned_user_id, 'status_id' => $lead->status_id, 'priority' => $lead->priority->value, 'source' => 'customer_portal', 'source_name' => 'Customer Portal']));

            return $lead->refresh()->load(['source', 'status', 'customer']);
        });
    }

    private function nextTicketNumber(): string
    {
        $prefix = 'TKT-'.now()->format('Y').'-';
        $last = CrmSupportTicket::query()->where('ticket_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('ticket_number');

        return $prefix.str_pad((string) (($last ? (int) str($last)->afterLast('-')->toString() : 0) + 1), 6, '0', STR_PAD_LEFT);
    }

    private function dispatchSupport(string $event, CrmSupportTicket $ticket): void
    {
        $this->events->dispatch(new SupportTicketEvent($event, $ticket->company_id, null, CrmSupportTicket::class, $ticket->id, ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'ticket_subject' => $ticket->subject, 'customer_name' => $ticket->customer?->company_name ?: $ticket->reported_by_name, 'assigned_user_id' => $ticket->assigned_to, 'priority' => $ticket->priority->value, 'status' => $ticket->status->value, 'due_at' => $ticket->due_at?->toIso8601String()]));
    }
}
