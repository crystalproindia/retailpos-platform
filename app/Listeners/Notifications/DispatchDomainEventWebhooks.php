<?php

namespace App\Listeners\Notifications;

use App\Events\Domain\DomainEventOccurred;
use App\Services\Notifications\WebhookService;

class DispatchDomainEventWebhooks
{
    public function __construct(private readonly WebhookService $webhookService) {}

    public function handle(DomainEventOccurred $event): void
    {
        $this->webhookService->queueForEvent($event->event, $event->eventLog);
    }
}
