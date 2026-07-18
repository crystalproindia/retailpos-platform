<?php

namespace App\Jobs\Notifications;

use App\Models\NotificationDelivery;
use App\Services\Notifications\EmailDeliveryService;
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

    public function handle(EmailDeliveryService $emailDelivery): void
    {
        $delivery = NotificationDelivery::query()->findOrFail($this->deliveryId);

        if ($delivery->channel !== 'email' || ! $delivery->recipient || ! filter_var($delivery->recipient, FILTER_VALIDATE_EMAIL)) {
            $delivery->update([
                'status' => 'failed',
                'failure_reason' => 'Email delivery requires a valid recipient address.',
                'failed_at' => now(),
            ]);

            return;
        }

        $emailDelivery->send($delivery);
    }

    public function failed(Throwable $exception): void
    {
        NotificationDelivery::query()
            ->whereKey($this->deliveryId)
            ->update([
                'status' => 'failed',
                'failure_reason' => 'Email transport could not complete delivery.',
                'failed_at' => now(),
                'next_retry_at' => now()->addMinutes(15),
            ]);
    }
}
