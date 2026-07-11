<?php

namespace App\Events\Domain;

use App\Contracts\Events\DomainEvent;
use App\Models\DomainEventLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DomainEventOccurred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly DomainEvent $event,
        public readonly DomainEventLog $eventLog,
    ) {
        //
    }
}
