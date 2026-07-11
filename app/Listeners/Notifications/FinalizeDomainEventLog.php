<?php

namespace App\Listeners\Notifications;

use App\Events\Domain\DomainEventOccurred;

class FinalizeDomainEventLog
{
    public function handle(DomainEventOccurred $event): void
    {
        $event->eventLog->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }
}
