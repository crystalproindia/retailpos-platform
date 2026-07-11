<?php

namespace App\Events\Domain\Crm;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\Concerns\SerializesDomainEvent;

class LeadStatusChanged extends SerializesDomainEvent implements DomainEvent
{
    public function eventKey(): string
    {
        return 'crm.lead.status_changed';
    }
}
