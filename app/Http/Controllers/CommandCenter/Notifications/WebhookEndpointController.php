<?php

namespace App\Http\Controllers\CommandCenter\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\StoreWebhookEndpointRequest;
use App\Http\Requests\Notifications\UpdateWebhookEndpointRequest;
use App\Models\WebhookEndpoint;
use App\Repositories\Notifications\WebhookRepository;
use App\Services\AuditLogger;
use App\Services\Notifications\WebhookService;
use App\Support\Events\EventCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebhookEndpointController extends Controller
{
    public function index(Request $request, WebhookRepository $webhookRepository, EventCatalog $eventCatalog): View
    {
        return view('command-center.notifications.webhooks.index', [
            'endpoints' => $webhookRepository->paginateEndpoints($request->user(), $request->only(['search', 'status'])),
            'eventOptions' => $eventCatalog->all(),
        ]);
    }

    public function store(StoreWebhookEndpointRequest $request, WebhookService $webhookService, AuditLogger $auditLogger): RedirectResponse
    {
        $endpoint = WebhookEndpoint::create($request->validated() + [
            'company_id' => $request->user()->company_id,
            'secret' => $webhookService->generateSecret(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $auditLogger->record('webhook.endpoint.created', $endpoint, 'Webhook endpoint created');

        return back()->with('status', 'Webhook endpoint created.');
    }

    public function update(UpdateWebhookEndpointRequest $request, WebhookRepository $webhookRepository, AuditLogger $auditLogger, int $webhook): RedirectResponse
    {
        $endpoint = $webhookRepository->findEndpointForUser($request->user(), $webhook);
        $endpoint->update($request->validated() + [
            'is_active' => $request->boolean('is_active'),
        ]);

        $auditLogger->record('webhook.endpoint.updated', $endpoint, 'Webhook endpoint updated');

        return back()->with('status', 'Webhook endpoint updated.');
    }

    public function toggle(Request $request, WebhookRepository $webhookRepository, AuditLogger $auditLogger, int $webhook): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.webhooks.manage'), 403);

        $endpoint = $webhookRepository->findEndpointForUser($request->user(), $webhook);
        $endpoint->update(['is_active' => ! $endpoint->is_active]);

        $auditLogger->record($endpoint->is_active ? 'webhook.endpoint.enabled' : 'webhook.endpoint.disabled', $endpoint, 'Webhook endpoint status changed');

        return back()->with('status', 'Webhook endpoint status updated.');
    }

    public function rotateSecret(Request $request, WebhookRepository $webhookRepository, WebhookService $webhookService, AuditLogger $auditLogger, int $webhook): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.webhooks.manage'), 403);

        $endpoint = $webhookRepository->findEndpointForUser($request->user(), $webhook);
        $endpoint->update(['secret' => $webhookService->generateSecret()]);

        $auditLogger->record('webhook.secret.rotated', $endpoint, 'Webhook secret rotated');

        return back()->with('status', 'Webhook secret rotated.');
    }

    public function retryDelivery(Request $request, WebhookRepository $webhookRepository, WebhookService $webhookService, AuditLogger $auditLogger, int $delivery): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.webhooks.retry'), 403);

        $webhookDelivery = $webhookRepository->findDeliveryForUser($request->user(), $delivery);
        $webhookService->retry($webhookDelivery);

        $auditLogger->record('webhook.delivery.retried', null, 'Webhook delivery manually retried', [
            'company_id' => $request->user()->company_id,
            'webhook_delivery_id' => $delivery,
        ]);

        return back()->with('status', 'Webhook delivery queued for retry.');
    }
}
