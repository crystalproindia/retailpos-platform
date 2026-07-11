<?php

namespace App\Events\Domain\Inventory;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\Concerns\SerializesDomainEvent;

class ReorderSuggested extends SerializesDomainEvent implements DomainEvent
{
    public function eventKey(): string
    {
        return 'inventory.reorder.suggested';
    }
}
