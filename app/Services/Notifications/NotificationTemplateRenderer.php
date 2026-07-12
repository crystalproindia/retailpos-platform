<?php

namespace App\Services\Notifications;

use App\Contracts\Events\DomainEvent;
use App\Models\NotificationTemplate;
use App\Support\Events\EventCatalog;

class NotificationTemplateRenderer
{
    public function __construct(private readonly EventCatalog $eventCatalog) {}

    /**
     * @return array<string, mixed>
     */
    public function render(DomainEvent $event, string $channel): array
    {
        $definition = $this->eventCatalog->find($event->eventKey()) ?? [];
        $payload = $event->payload();
        $template = NotificationTemplate::query()
            ->where(function ($query) use ($event): void {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $event->companyId());
            })
            ->where('event_key', $event->eventKey())
            ->where('channel', $channel)
            ->where('is_active', true)
            ->orderByRaw('company_id is null')
            ->latest('version')
            ->first();

        $title = $template?->subject ?: ($definition['name'] ?? str($event->eventKey())->replace('.', ' ')->headline()->toString());
        $message = $template?->body ?: $this->fallbackMessage($event, $definition);

        return [
            'title' => $this->interpolate($title, $payload),
            'message' => $this->interpolate($message, $payload),
            'severity' => $definition['severity'] ?? 'info',
            'event_key' => $event->eventKey(),
            'action_url' => $this->actionUrl($event),
            'icon' => $definition['category'] === 'CRM' ? 'crm' : 'bell',
            'metadata' => [
                'category' => $definition['category'] ?? 'System',
                'correlation_id' => $event->correlationId(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function interpolate(string $value, array $payload): string
    {
        foreach ($payload as $key => $replacement) {
            if (is_scalar($replacement) || $replacement === null) {
                $value = str_replace('{{ '.$key.' }}', (string) $replacement, $value);
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function fallbackMessage(DomainEvent $event, array $definition): string
    {
        return ($definition['description'] ?? 'A platform event occurred.').' Event: '.$event->eventKey().'.';
    }

    private function actionUrl(DomainEvent $event): ?string
    {
        return match ($event->eventKey()) {
            'crm.lead.created', 'crm.lead.assigned', 'crm.lead.status_changed', 'crm.lead.converted' => $event->aggregateId() ? route('crm.leads.show', $event->aggregateId()) : null,
            'crm.follow_up.due', 'crm.follow_up.overdue' => route('crm.followups.index'),
            'cms.page.published', 'cms.page.unpublished' => $event->aggregateId() ? route('cms.pages.edit', $event->aggregateId()) : null,
            'purchase.request.created', 'purchase.request.submitted', 'purchase.request.approved', 'purchase.request.rejected', 'purchase.request.converted_to_po', 'purchase.reorder_request.created' => $event->aggregateId() ? route('purchases.requests.show', $event->aggregateId()) : null,
            'purchase.order.created', 'purchase.order.submitted', 'purchase.order.approved', 'purchase.order.sent', 'purchase.order.cancelled' => $event->aggregateId() ? route('purchases.orders.show', $event->aggregateId()) : null,
            'purchase.goods_received' => $event->aggregateId() ? route('purchases.grn.show', $event->aggregateId()) : null,
            'purchase.return.created', 'purchase.return.approved', 'purchase.return.completed' => $event->aggregateId() ? route('purchases.returns.show', $event->aggregateId()) : null,
            'purchase.supplier.created', 'purchase.supplier.updated', 'purchase.supplier.score_updated' => $event->aggregateId() ? route('purchases.suppliers.show', $event->aggregateId()) : null,
            default => route('notifications.index'),
        };
    }
}
