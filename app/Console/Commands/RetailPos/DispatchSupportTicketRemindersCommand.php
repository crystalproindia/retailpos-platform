<?php

namespace App\Console\Commands\RetailPos;

use App\Enums\Crm\SupportTicketStatus;
use App\Events\Domain\Crm\SupportTicketEvent;
use App\Models\Crm\CrmSupportTicket;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Console\Command;

class DispatchSupportTicketRemindersCommand extends Command
{
    protected $signature = 'retailpos:support-ticket-reminders';

    protected $description = 'Dispatch idempotent reminders for overdue and waiting support tickets.';

    public function handle(DomainEventDispatcher $events): int
    {
        $count = 0;

        CrmSupportTicket::query()
            ->withCount(['messages as customer_safe_reply_count' => fn ($query) => $query->where('visibility', 'customer_safe')])
            ->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value])
            ->orderBy('id')
            ->chunkById(100, function ($tickets) use ($events, &$count): void {
                foreach ($tickets as $ticket) {
                    if ($ticket->first_response_due_at?->isPast() && $ticket->customer_safe_reply_count === 0) {
                        $this->dispatch($events, 'crm.support_ticket_overdue', $ticket, 'first-response');
                        $count++;
                    }

                    if ($ticket->due_at?->isPast()) {
                        $this->dispatch($events, 'crm.support_ticket_overdue', $ticket, 'resolution');
                        $count++;
                    }

                    if ($ticket->status === SupportTicketStatus::WaitingForInternalTeam && $ticket->updated_at->lte(now()->subHours((int) config('crm-support.waiting_reminder_hours', 24)))) {
                        $this->dispatch($events, 'crm.support_ticket_waiting_internal', $ticket, 'waiting');
                        $count++;
                    }
                }
            });

        $this->info("Dispatched {$count} support ticket reminder checks.");

        return self::SUCCESS;
    }

    private function dispatch(DomainEventDispatcher $events, string $event, CrmSupportTicket $ticket, string $reason): void
    {
        $events->dispatch(new SupportTicketEvent($event, $ticket->company_id, null, CrmSupportTicket::class, $ticket->id, [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'ticket_subject' => $ticket->subject,
            'customer_name' => $ticket->reported_by_name,
            'assigned_user_id' => $ticket->assigned_to,
            'priority' => $ticket->priority->value,
            'status' => $ticket->status->value,
            'due_at' => $ticket->due_at?->toIso8601String(),
        ], "{$event}:{$reason}:{$ticket->id}:".now()->toDateString()));
    }
}
