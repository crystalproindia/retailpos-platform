<?php

namespace App\Jobs\Notifications;

use App\Models\NotificationDelivery;
use App\Notifications\PlatformNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendNotificationDeliveryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()->with('user')->findOrFail($this->deliveryId);

        if (! $delivery->user || $delivery->channel !== 'email') {
            $delivery->update([
                'status' => 'failed',
                'failure_reason' => 'Email delivery requires a valid recipient user.',
                'failed_at' => now(),
            ]);

            return;
        }

        $delivery->update([
            'status' => 'sending',
            'attempt_count' => $delivery->attempt_count + 1,
            'sent_at' => now(),
        ]);

        $payload = $delivery->payload ?? [];
        $delivery->user->notify(new PlatformNotification(
            channel: 'email',
            eventKey: $delivery->event_key,
            title: $payload['title'] ?? str($delivery->event_key)->replace('.', ' ')->headline()->toString(),
            message: $payload['message'] ?? '',
            actionUrl: $payload['action_url'] ?? null,
            severity: $payload['severity'] ?? 'info',
        ));

        $delivery->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'failure_reason' => null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        NotificationDelivery::query()
            ->whereKey($this->deliveryId)
            ->update([
                'status' => 'failed',
                'failure_reason' => str($exception->getMessage())->limit(500)->toString(),
                'failed_at' => now(),
                'next_retry_at' => now()->addMinutes(15),
            ]);
    }
}
