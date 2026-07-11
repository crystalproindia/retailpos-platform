<?php

namespace App\Events\Domain\System;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\Concerns\SerializesDomainEvent;

class SettingsUpdated extends SerializesDomainEvent implements DomainEvent
{
    public function eventKey(): string
    {
        return 'system.settings.updated';
    }
}
