<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $channel,
        private readonly string $eventKey,
        private readonly string $title,
        private readonly string $message,
        private readonly ?string $actionUrl = null,
        private readonly string $severity = 'info',
        private readonly ?string $icon = null,
        private readonly ?string $aggregateType = null,
        private readonly ?int $aggregateId = null,
        private readonly ?array $metadata = null,
    ) {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [$this->channel === 'email' ? 'mail' : 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->message);

        if ($this->actionUrl) {
            $mail->action('Open in Command Center', $this->actionUrl);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'event_key' => $this->eventKey,
            'severity' => $this->severity,
            'action_url' => $this->actionUrl,
            'icon' => $this->icon,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'occurred_at' => now()->toISOString(),
            'metadata' => $this->metadata,
        ];
    }
}
