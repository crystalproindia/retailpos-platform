<?php

namespace App\Events\Domain\Crm;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\Concerns\SerializesDomainEvent;

class LeadConverted extends SerializesDomainEvent implements DomainEvent
{
    public function eventKey(): string
    {
        return 'crm.lead.converted';
    }
}
