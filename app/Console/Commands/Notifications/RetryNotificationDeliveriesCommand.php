<?php

namespace App\Console\Commands\Notifications;

use App\Jobs\Notifications\SendNotificationDeliveryJob;
use App\Jobs\Notifications\SendWebhookDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class RetryNotificationDeliveriesCommand extends Command
{
    protected $signature = 'notifications:retry-failed-deliveries';

    protected $description = 'Queue retry attempts for failed notification and webhook deliveries.';

    public function handle(): int
    {
        $notificationCount = 0;
        $webhookCount = 0;

        NotificationDelivery::query()
            ->where('status', 'failed')
            ->where('channel', 'email')
            ->where(function ($query): void {
                $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->chunkById(100, function ($deliveries) use (&$notificationCount): void {
                foreach ($deliveries as $delivery) {
                    $delivery->update(['status' => 'queued', 'queued_at' => now()]);
                    SendNotificationDeliveryJob::dispatch($delivery->id);
                    $notificationCount++;
                }
            });

        WebhookDelivery::query()
            ->where('status', 'failed')
            ->where(function ($query): void {
                $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->chunkById(100, function ($deliveries) use (&$webhookCount): void {
                foreach ($deliveries as $delivery) {
                    $delivery->update(['status' => 'queued', 'next_retry_at' => null]);
                    SendWebhookDeliveryJob::dispatch($delivery->id);
                    $webhookCount++;
                }
            });

        $this->info("Queued {$notificationCount} notification retries and {$webhookCount} webhook retries.");

        return self::SUCCESS;
    }
}
