<?php

namespace App\Services\Events;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\DomainEventOccurred;
use App\Models\DomainEventLog;
use Illuminate\Database\QueryException;

class DomainEventDispatcher
{
    public function dispatch(DomainEvent $event): DomainEventLog
    {
        try {
            $eventLog = DomainEventLog::create([
                'company_id' => $event->companyId(),
                'user_id' => $event->actorId(),
                'event_key' => $event->eventKey(),
                'event_class' => $event::class,
                'aggregate_type' => $event->aggregateType(),
                'aggregate_id' => $event->aggregateId(),
                'correlation_id' => $event->correlationId(),
                'causation_id' => $event->causationId(),
                'payload' => $event->payload(),
                'occurred_at' => $event->occurredAt(),
                'status' => 'recorded',
            ]);
        } catch (QueryException) {
            return DomainEventLog::query()
                ->where('correlation_id', $event->correlationId())
                ->firstOrFail();
        }

        event($event);
        event(new DomainEventOccurred($event, $eventLog));

        return $eventLog;
    }
}
