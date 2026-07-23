<?php

namespace App\Jobs\Saas;

use App\Models\SaasBillingWebhookEvent;
use App\Services\Saas\SaasBillingWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessSaasBillingWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;
    public function __construct(public readonly int $eventId) {}
    public function backoff(): array { return [60, 300, 900]; }
    public function handle(SaasBillingWebhookService $webhooks): void { $webhooks->process(SaasBillingWebhookEvent::query()->findOrFail($this->eventId)); }
    public function failed(Throwable $exception): void { if ($event = SaasBillingWebhookEvent::query()->find($this->eventId)) app(SaasBillingWebhookService::class)->fail($event); }
}
