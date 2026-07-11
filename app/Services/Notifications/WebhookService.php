<?php

namespace App\Services\Notifications;

use App\Contracts\Events\DomainEvent;
use App\Jobs\Notifications\SendWebhookDeliveryJob;
use App\Models\DomainEventLog;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class WebhookService
{
    public function queueForEvent(DomainEvent $event, DomainEventLog $eventLog): void
    {
        WebhookEndpoint::query()
            ->where('company_id', $event->companyId())
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => in_array($event->eventKey(), $endpoint->subscribed_events ?? [], true))
            ->each(function (WebhookEndpoint $endpoint) use ($event, $eventLog): void {
                $delivery = WebhookDelivery::create([
                    'company_id' => $endpoint->company_id,
                    'webhook_endpoint_id' => $endpoint->id,
                    'domain_event_log_id' => $eventLog->id,
                    'event_key' => $event->eventKey(),
                    'payload' => $this->payload($event, $eventLog),
                    'status' => 'queued',
                    'next_retry_at' => null,
                ]);

                SendWebhookDeliveryJob::dispatch($delivery->id);
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(DomainEvent $event, DomainEventLog $eventLog): array
    {
        return [
            'event_id' => $eventLog->id,
            'event_key' => $event->eventKey(),
            'occurred_at' => $event->occurredAt()->toISOString(),
            'company_id' => $event->companyId(),
            'aggregate_type' => $event->aggregateType(),
            'aggregate_id' => $event->aggregateId(),
            'correlation_id' => $event->correlationId(),
            'data' => $event->payload(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function signature(WebhookEndpoint $endpoint, array $payload, int $timestamp): string
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);
    }

    public function generateSecret(): string
    {
        return 'whsec_'.Str::random(48);
    }

    public function retryDelayMinutes(int $attemptCount): int
    {
        return min(60, max(5, $attemptCount * 5));
    }

    public function retry(WebhookDelivery $delivery): WebhookDelivery
    {
        $delivery->update([
            'status' => 'queued',
            'next_retry_at' => null,
        ]);

        SendWebhookDeliveryJob::dispatch($delivery->id);

        return $delivery;
    }
}
