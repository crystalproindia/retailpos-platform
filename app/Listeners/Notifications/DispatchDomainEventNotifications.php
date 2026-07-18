<?php

namespace App\Listeners\Notifications;

use App\Events\Domain\DomainEventOccurred;
use App\Services\Notifications\EmailNotificationWorkflow;
use App\Services\Notifications\NotificationService;

class DispatchDomainEventNotifications
{
    public function __construct(private readonly NotificationService $notificationService, private readonly EmailNotificationWorkflow $emailWorkflow) {}

    public function handle(DomainEventOccurred $event): void
    {
        $this->notificationService->dispatchForEvent($event->event, $event->eventLog);
        $this->emailWorkflow->queueFor($event->event);
    }
}
