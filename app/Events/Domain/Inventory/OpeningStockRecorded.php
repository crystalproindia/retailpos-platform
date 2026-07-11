<?php

namespace App\Events\Domain\Inventory;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\Concerns\SerializesDomainEvent;

class OpeningStockRecorded extends SerializesDomainEvent implements DomainEvent
{
    public function eventKey(): string
    {
        return 'inventory.stock.opening_recorded';
    }
}
