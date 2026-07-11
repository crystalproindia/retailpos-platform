<?php

namespace App\Jobs\Notifications;

use App\Models\WebhookDelivery;
use App\Services\Notifications\WebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendWebhookDeliveryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public readonly int $webhookDeliveryId) {}

    public function handle(WebhookService $webhookService): void
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->findOrFail($this->webhookDeliveryId);
        $endpoint = $delivery->endpoint;

        if (! $endpoint || ! $endpoint->is_active) {
            $delivery->update([
                'status' => 'failed',
                'failed_at' => now(),
                'response_body' => 'Webhook endpoint is disabled or missing.',
            ]);

            return;
        }

        $timestamp = now()->timestamp;
        $signature = $webhookService->signature($endpoint, $delivery->payload, $timestamp);

        $delivery->update([
            'status' => 'sending',
            'attempt_count' => $delivery->attempt_count + 1,
            'sent_at' => now(),
            'signature' => $signature,
        ]);

        $response = Http::timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-RetailPOS-Event' => $delivery->event_key,
                'X-RetailPOS-Timestamp' => (string) $timestamp,
                'X-RetailPOS-Signature' => $signature,
            ])
            ->post($endpoint->url, $delivery->payload);

        if ($response->successful()) {
            $delivery->update([
                'status' => 'delivered',
                'response_code' => $response->status(),
                'response_body' => str($response->body())->limit(1000)->toString(),
                'completed_at' => now(),
                'failed_at' => null,
                'next_retry_at' => null,
            ]);

            $endpoint->update([
                'last_success_at' => now(),
                'failure_count' => 0,
            ]);

            return;
        }

        $delivery->update([
            'status' => 'failed',
            'response_code' => $response->status(),
            'response_body' => str($response->body())->limit(1000)->toString(),
            'failed_at' => now(),
            'next_retry_at' => now()->addMinutes($webhookService->retryDelayMinutes($delivery->attempt_count)),
        ]);

        $endpoint->increment('failure_count');
        $endpoint->forceFill(['last_failure_at' => now()])->save();
    }

    public function failed(Throwable $exception): void
    {
        WebhookDelivery::query()
            ->whereKey($this->webhookDeliveryId)
            ->update([
                'status' => 'failed',
                'response_body' => str($exception->getMessage())->limit(1000)->toString(),
                'failed_at' => now(),
                'next_retry_at' => now()->addMinutes(15),
            ]);
    }
}
