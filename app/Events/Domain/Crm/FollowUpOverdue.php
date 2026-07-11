<?php

namespace App\Events\Domain\Crm;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\Concerns\SerializesDomainEvent;

class FollowUpOverdue extends SerializesDomainEvent implements DomainEvent
{
    public function eventKey(): string
    {
        return 'crm.follow_up.overdue';
    }
}
