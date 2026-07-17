<?php

namespace App\Enums\Crm;

enum SupportTicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string { return str($this->value)->headline()->toString(); }
}
