<?php

namespace App\Contracts\Notifications;

use App\Contracts\Events\DomainEvent;
use App\Models\NotificationDelivery;
use App\Models\User;

interface NotificationChannel
{
    /**
     * @param  array<string, mixed>  $message
     */
    public function send(User $recipient, DomainEvent $event, array $message, NotificationDelivery $delivery): NotificationDelivery;
}
