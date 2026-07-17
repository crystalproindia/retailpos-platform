<?php

namespace App\Enums\Crm;

enum SupportTicketStatus: string
{
    case New = 'new';
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingForCustomer = 'waiting_for_customer';
    case WaitingForInternalTeam = 'waiting_for_internal_team';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Reopened = 'reopened';

    public function label(): string { return str($this->value)->replace('_', ' ')->headline()->toString(); }
    public function isOpen(): bool { return ! in_array($this, [self::Resolved, self::Closed], true); }
}
