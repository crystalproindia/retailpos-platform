<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\Events\DomainEvent;
use App\Contracts\Notifications\NotificationChannel;
use App\Models\NotificationDelivery;
use App\Models\User;

class UnsupportedNotificationChannel implements NotificationChannel
{
    public function __construct(private readonly string $channel) {}

    /**
     * @param  array<string, mixed>  $message
     */
    public function send(User $recipient, DomainEvent $event, array $message, NotificationDelivery $delivery): NotificationDelivery
    {
        return tap($delivery)->update([
            'status' => 'unsupported',
            'failed_at' => now(),
            'failure_reason' => str($this->channel)->headline().' delivery is not enabled in Phase 2.5.',
            'payload' => $message,
        ]);
    }
}
