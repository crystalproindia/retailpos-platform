<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\Events\DomainEvent;
use App\Contracts\Notifications\NotificationChannel;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\PlatformNotification;

class DatabaseNotificationChannel implements NotificationChannel
{
    /**
     * @param  array<string, mixed>  $message
     */
    public function send(User $recipient, DomainEvent $event, array $message, NotificationDelivery $delivery): NotificationDelivery
    {
        $notification = new PlatformNotification(
            channel: 'database',
            eventKey: $event->eventKey(),
            title: $message['title'],
            message: $message['message'],
            actionUrl: $message['action_url'] ?? null,
            severity: $message['severity'] ?? 'info',
            icon: $message['icon'] ?? null,
            aggregateType: $event->aggregateType(),
            aggregateId: $event->aggregateId(),
            metadata: $message['metadata'] ?? null,
        );

        $recipient->notify($notification);

        return tap($delivery)->update([
            'notification_id' => $notification->id,
            'status' => 'delivered',
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);
    }
}
