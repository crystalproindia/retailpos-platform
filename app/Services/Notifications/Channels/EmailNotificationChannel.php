<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\Events\DomainEvent;
use App\Contracts\Notifications\NotificationChannel;
use App\Jobs\Notifications\SendNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\User;

class EmailNotificationChannel implements NotificationChannel
{
    /**
     * @param  array<string, mixed>  $message
     */
    public function send(User $recipient, DomainEvent $event, array $message, NotificationDelivery $delivery): NotificationDelivery
    {
        $delivery->update([
            'status' => 'queued',
            'queued_at' => now(),
            'payload' => $message,
        ]);

        SendNotificationDeliveryJob::dispatch($delivery->id);

        return $delivery;
    }
}
