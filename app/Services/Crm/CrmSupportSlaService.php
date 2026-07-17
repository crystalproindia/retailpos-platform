<?php

namespace App\Services\Crm;

use App\Enums\Crm\SupportTicketStatus;
use App\Models\Crm\CrmSupportTicket;
use Carbon\CarbonImmutable;

class CrmSupportSlaService
{
    /** @return array{first_response_due_at: CarbonImmutable, due_at: CarbonImmutable} */
    public function deadlines(string $priority, ?CarbonImmutable $from = null): array
    {
        $rules = config("crm-support.sla.{$priority}", config('crm-support.sla.normal'));
        $from ??= CarbonImmutable::now();

        return ['first_response_due_at' => $from->addHours((int) $rules['first_response_hours']), 'due_at' => $from->addDays((int) $rules['resolution_days'])];
    }

    public function isOverdue(CrmSupportTicket $ticket): bool
    {
        return $ticket->status->isOpen() && $ticket->due_at?->isPast();
    }

    public function isFirstResponseOverdue(CrmSupportTicket $ticket): bool
    {
        return $ticket->status->isOpen() && $ticket->first_response_due_at?->isPast() && ! $ticket->messages()->where('visibility', 'customer_safe')->exists();
    }

    public function isAtRisk(CrmSupportTicket $ticket): bool
    {
        if (! $ticket->status->isOpen()) return false;
        return ($ticket->first_response_due_at && $ticket->first_response_due_at->isFuture() && $ticket->first_response_due_at->diffInMinutes(now()) <= 60)
            || ($ticket->due_at && $ticket->due_at->isFuture() && $ticket->due_at->diffInHours(now()) <= 24);
    }
}
