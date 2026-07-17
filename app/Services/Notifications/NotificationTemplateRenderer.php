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

        $title = $template?->subject ?: $this->fallbackTitle($event, $definition, $channel);
        $message = $template?->body ?: $this->fallbackMessage($event, $definition, $channel);

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
                'notification_type' => $this->notificationType($event),
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
    private function fallbackMessage(DomainEvent $event, array $definition, string $channel): string
    {
        if ($event->eventKey() === 'crm.lead.created') {
            $payload = $event->payload();
            $contact = $payload['contact_name'] ?? 'A prospect';
            $business = filled($payload['business_name'] ?? null) ? ' from '.$payload['business_name'] : '';

            $message = match ($this->notificationType($event)) {
                'demo_request_received' => "{$contact}{$business} requested a product demo.",
                'pricing_enquiry_received' => "{$contact}{$business} submitted a pricing enquiry.",
                default => "{$contact}{$business} submitted a new lead.",
            };

            if ($channel !== 'email') {
                return $message;
            }

            return $message."\n\n".implode("\n", array_filter([
                'Name: '.($payload['contact_name'] ?? 'Not provided'),
                'Company: '.($payload['business_name'] ?? 'Not provided'),
                'Phone: '.($payload['phone'] ?? 'Not provided'),
                'Email: '.($payload['email'] ?? 'Not provided'),
                'Business type: '.($payload['business_type'] ?? 'Not provided'),
                'Source: '.($payload['source_name'] ?? $payload['source'] ?? 'Not provided'),
                filled($payload['requirement'] ?? null) ? 'Requirement: '.$payload['requirement'] : null,
            ]));
        }

        if ($event->eventKey() === 'crm.follow_up.due' && ($event->payload()['lead_title'] ?? null)) {
            return 'Follow up with '.$event->payload()['lead_title'].' now.';
        }

        if ($event->eventKey() === 'crm.demo.scheduled') {
            $name = $event->payload()['business_name'] ?? $event->payload()['lead_title'] ?? 'this lead';

            return 'Demo scheduled for '.$name.' on '.($event->payload()['scheduled_at'] ?? 'the selected time').'.';
        }

        if ($event->eventKey() === 'crm.pipeline.stage_changed') {
            $name = $event->payload()['business_name'] ?? $event->payload()['lead_title'] ?? 'this lead';

            return $name.' moved to '.($event->payload()['to_stage'] ?? 'a new pipeline stage').'.';
        }

        if (in_array($event->eventKey(), ['crm.lead_score.hot', 'crm.lead_score.at_risk', 'crm.lead_followup_overdue', 'crm.payment_followup_required'], true)) {
            $name = $event->payload()['business_name'] ?? $event->payload()['lead_title'] ?? 'this lead';

            return match ($event->eventKey()) {
                'crm.lead_score.hot' => "{$name} is now a hot lead (".($event->payload()['score'] ?? '—').'/100).',
                'crm.lead_score.at_risk' => "{$name} is a high-value lead at risk. ".($event->payload()['next_best_action'] ?? ''),
                'crm.lead_followup_overdue' => "{$name} needs an overdue follow-up. ".($event->payload()['next_best_action'] ?? ''),
                default => "{$name} has a partial-payment follow-up to complete.",
            };
        }

        if ($event->eventKey() === 'crm.demo.google_calendar_synced') {
            return 'Demo synced to Google Calendar for '.($event->payload()['business_name'] ?? $event->payload()['lead_title'] ?? 'this lead').'.';
        }

        if ($event->eventKey() === 'crm.demo.google_calendar_sync_failed') {
            return 'Google Calendar sync needs attention for '.($event->payload()['business_name'] ?? $event->payload()['lead_title'] ?? 'this lead').'.';
        }

        if (in_array($event->eventKey(), ['crm.quotation.created', 'crm.quotation.sent', 'crm.quotation.accepted', 'crm.quotation.rejected'], true)) {
            $number = $event->payload()['quotation_number'] ?? 'this quotation';
            $name = $event->payload()['business_name'] ?? $event->payload()['lead_title'] ?? 'this lead';

            return match ($event->eventKey()) {
                'crm.quotation.created' => "Quotation {$number} was created for {$name}.",
                'crm.quotation.sent' => "Quotation {$number} was marked as sent for {$name}.",
                'crm.quotation.accepted' => "Quotation {$number} was accepted by {$name}.",
                default => "Quotation {$number} was rejected for {$name}.",
            };
        }

        if (in_array($event->eventKey(), ['crm.proforma.created', 'crm.proforma.sent', 'crm.proforma.payment_recorded', 'crm.proforma.fully_paid', 'crm.proforma.share_failed'], true)) {
            $number = $event->payload()['proforma_number'] ?? 'this proforma invoice';

            return match ($event->eventKey()) {
                'crm.proforma.created' => "Proforma invoice {$number} created.",
                'crm.proforma.sent' => "Proforma invoice {$number} sent to customer.",
                'crm.proforma.payment_recorded' => 'Payment of '.($event->payload()['currency'] ?? 'INR').' '.number_format((float) ($event->payload()['amount'] ?? 0), 2)." recorded for {$number}.",
                'crm.proforma.fully_paid' => "Proforma invoice {$number} is fully paid.",
                default => "Email sending failed for {$number}.",
            };
        }

        if ($event->eventKey() === 'crm.customer.created') {
            return 'Lead '.($event->payload()['lead_title'] ?? 'record').' was converted to customer '.($event->payload()['customer_code'] ?? $event->payload()['customer_name'] ?? 'account').'.';
        }

        if (str($event->eventKey())->startsWith('crm.onboarding.')) {
            $number = $event->payload()['onboarding_number'] ?? 'this onboarding';
            $name = $event->payload()['customer_name'] ?? 'the customer';

            return match ($event->eventKey()) {
                'crm.onboarding.started' => "Onboarding {$number} started for {$name}.",
                'crm.onboarding.task_assigned' => "An onboarding responsibility was assigned for {$name}.",
                'crm.onboarding.task_overdue' => 'An onboarding task is overdue for '.$name.'.',
                'crm.onboarding.target_go_live_missed' => 'The target go-live date has passed for '.$name.'.',
                'crm.onboarding.document_pending' => 'A required onboarding document is still pending for '.$name.'.',
                'crm.onboarding.blocked' => "Onboarding {$number} is blocked.",
                'crm.onboarding.go_live_ready' => "Onboarding {$number} is ready for go-live.",
                'crm.onboarding.live' => "{$name} is now live.",
                'crm.onboarding.on_hold' => "Onboarding {$number} is on hold.",
                'crm.onboarding.cancelled' => "Onboarding {$number} was cancelled.",
                default => "Onboarding {$number} was updated.",
            };
        }

        return ($definition['description'] ?? 'A platform event occurred.').' Event: '.$event->eventKey().'.';
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function fallbackTitle(DomainEvent $event, array $definition, string $channel): string
    {
        if ($event->eventKey() === 'crm.lead.created' && $channel === 'email') {
            return match ($this->notificationType($event)) {
                'demo_request_received' => 'New RetailPOS Demo Request',
                'pricing_enquiry_received' => 'New RetailPOS Pricing Enquiry',
                default => 'New RetailPOS Lead: '.($event->payload()['source_name'] ?? 'Website Contact'),
            };
        }

        return match ($this->notificationType($event)) {
            'demo_request_received' => 'New demo request',
            'pricing_enquiry_received' => 'New pricing enquiry',
            'new_lead_received' => 'New lead received',
            'follow_up_due' => 'Lead follow-up due',
            'demo_scheduled' => 'Demo scheduled',
            'demo_google_calendar_synced' => 'Demo synced to Google Calendar',
            'demo_google_calendar_sync_failed' => 'Google Calendar sync failed',
            'quotation_created' => 'Quotation created',
            'quotation_sent' => 'Quotation sent',
            'quotation_accepted' => 'Quotation accepted',
            'quotation_rejected' => 'Quotation rejected',
            'proforma_created' => 'Proforma invoice created',
            'proforma_sent' => 'Proforma invoice sent',
            'proforma_payment_recorded' => 'Proforma payment recorded',
            'proforma_fully_paid' => 'Proforma invoice fully paid',
            'proforma_share_failed' => 'Proforma sharing failed',
            'crm_customer_created' => 'CRM customer created',
            default => $definition['name'] ?? str($event->eventKey())->replace('.', ' ')->headline()->toString(),
        };
    }

    private function notificationType(DomainEvent $event): string
    {
        if ($event->eventKey() === 'crm.lead.created') {
            return match ($event->payload()['lead_type'] ?? null) {
                'book_demo' => 'demo_request_received',
                'pricing_enquiry' => 'pricing_enquiry_received',
                default => 'new_lead_received',
            };
        }

        return match ($event->eventKey()) {
            'crm.follow_up.due' => 'follow_up_due',
            'crm.demo.scheduled' => 'demo_scheduled',
            'crm.demo.google_calendar_synced' => 'demo_google_calendar_synced',
            'crm.demo.google_calendar_sync_failed' => 'demo_google_calendar_sync_failed',
            'crm.quotation.created' => 'quotation_created',
            'crm.quotation.sent' => 'quotation_sent',
            'crm.quotation.accepted' => 'quotation_accepted',
            'crm.quotation.rejected' => 'quotation_rejected',
            'crm.proforma.created' => 'proforma_created',
            'crm.proforma.sent' => 'proforma_sent',
            'crm.proforma.payment_recorded' => 'proforma_payment_recorded',
            'crm.proforma.fully_paid' => 'proforma_fully_paid',
            'crm.proforma.share_failed' => 'proforma_share_failed',
            'crm.customer.created' => 'crm_customer_created',
            'crm.onboarding.started' => 'onboarding_started',
            'crm.onboarding.task_assigned' => 'onboarding_task_assigned',
            'crm.onboarding.task_overdue' => 'onboarding_task_overdue',
            'crm.onboarding.target_go_live_missed' => 'onboarding_target_go_live_missed',
            'crm.onboarding.document_pending' => 'onboarding_document_pending',
            'crm.onboarding.blocked' => 'onboarding_blocked',
            'crm.onboarding.go_live_ready' => 'onboarding_go_live_ready',
            'crm.onboarding.live' => 'onboarding_live',
            'crm.onboarding.on_hold' => 'onboarding_on_hold',
            'crm.onboarding.cancelled' => 'onboarding_cancelled',
            'crm.lead.assigned' => 'lead_assigned',
            'crm.lead.status_changed' => 'lead_status_changed',
            'crm.pipeline.stage_changed' => 'pipeline_stage_changed',
            'crm.lead_score.hot' => 'lead_score_hot',
            'crm.lead_score.at_risk' => 'lead_score_at_risk',
            'crm.lead_followup_overdue' => 'lead_followup_overdue',
            'crm.payment_followup_required' => 'payment_followup_required',
            default => $event->eventKey(),
        };
    }

    private function actionUrl(DomainEvent $event): ?string
    {
        return match ($event->eventKey()) {
            'pos.sale.held' => route('pos.index'),
            'pos.sale.completed' => $event->aggregateId() ? route('pos.receipts.show', $event->aggregateId()) : route('pos.index'),
            'pos.offline.bill.queued', 'pos.offline.sync.started', 'pos.offline.sync.completed', 'pos.offline.sync.failed', 'pos.offline.sync.record_failed', 'pos.offline.sync.warning' => route('pos.offline.index'),
            'crm.lead.created', 'crm.lead.assigned', 'crm.lead.status_changed', 'crm.pipeline.stage_changed', 'crm.lead.converted', 'crm.lead_score.hot', 'crm.lead_score.at_risk', 'crm.lead_followup_overdue', 'crm.payment_followup_required' => $event->aggregateId() ? route('crm.leads.show', $event->aggregateId()) : null,
            'crm.demo.scheduled', 'crm.demo.google_calendar_synced', 'crm.demo.google_calendar_sync_failed' => ($event->payload()['lead_id'] ?? null) ? route('crm.leads.show', $event->payload()['lead_id']) : null,
            'crm.quotation.created', 'crm.quotation.sent', 'crm.quotation.accepted', 'crm.quotation.rejected' => $event->aggregateId() ? route('crm.quotations.show', $event->aggregateId()) : null,
            'crm.proforma.created', 'crm.proforma.sent', 'crm.proforma.payment_recorded', 'crm.proforma.fully_paid', 'crm.proforma.share_failed' => $event->aggregateId() ? route('crm.proformas.show', $event->aggregateId()) : null,
            'crm.customer.created' => $event->aggregateId() ? route('crm.customers.show', $event->aggregateId()) : null,
            'crm.onboarding.started', 'crm.onboarding.task_assigned', 'crm.onboarding.task_overdue', 'crm.onboarding.target_go_live_missed', 'crm.onboarding.document_pending', 'crm.onboarding.blocked', 'crm.onboarding.go_live_ready', 'crm.onboarding.live', 'crm.onboarding.on_hold', 'crm.onboarding.cancelled', 'crm.onboarding.updated' => $event->aggregateId() ? route('crm.onboarding.show', $event->aggregateId()) : null,
            'crm.follow_up.due', 'crm.follow_up.overdue' => $event->payload()['lead_id'] ?? null
                ? route('crm.leads.show', $event->payload()['lead_id'])
                : route('crm.followups.index'),
            'cms.page.published', 'cms.page.unpublished' => $event->aggregateId() ? route('cms.pages.edit', $event->aggregateId()) : null,
            'cms.branding.updated' => route('cms.branding.index'),
            'cms.theme.updated' => route('cms.theme.index'),
            'cms.client_logo.created', 'cms.client_logo.updated' => route('cms.client-logos.index'),
            'cms.case_study.created', 'cms.case_study.published', 'cms.case_study.unpublished' => $event->aggregateId() ? route('cms.case-studies.edit', $event->aggregateId()) : route('cms.case-studies.index'),
            'cms.testimonial.created' => route('cms.testimonials.index'),
            'cms.trust_metric.updated' => route('cms.trust-metrics.index'),
            'cms.cta.updated' => route('cms.ctas.index'),
            'cms.seo.updated' => route('cms.seo.index'),
            'purchase.request.created', 'purchase.request.submitted', 'purchase.request.approved', 'purchase.request.rejected', 'purchase.request.converted_to_po', 'purchase.reorder_request.created' => $event->aggregateId() ? route('purchases.requests.show', $event->aggregateId()) : null,
            'purchase.order.created', 'purchase.order.submitted', 'purchase.order.approved', 'purchase.order.sent', 'purchase.order.cancelled' => $event->aggregateId() ? route('purchases.orders.show', $event->aggregateId()) : null,
            'purchase.goods_received' => $event->aggregateId() ? route('purchases.grn.show', $event->aggregateId()) : null,
            'purchase.return.created', 'purchase.return.approved', 'purchase.return.completed' => $event->aggregateId() ? route('purchases.returns.show', $event->aggregateId()) : null,
            'purchase.supplier.created', 'purchase.supplier.updated', 'purchase.supplier.score_updated' => $event->aggregateId() ? route('purchases.suppliers.show', $event->aggregateId()) : null,
            'promotion.campaign.created', 'promotion.campaign.updated' => $event->aggregateId() ? route('promotions.campaigns.show', $event->aggregateId()) : route('promotions.campaigns.index'),
            'promotion.rule.created', 'promotion.rule.updated', 'promotion.rule.activated', 'promotion.rule.paused', 'promotion.rule.expired', 'promotion.approval.required' => $event->aggregateId() ? route('promotions.rules.show', $event->aggregateId()) : route('promotions.rules.index'),
            'promotion.coupon.created', 'promotion.coupon.used' => route('promotions.coupons.index'),
            'promotion.simulation.ran' => route('promotions.simulator.index'),
            'customer.created', 'customer.updated', 'customer.deleted', 'customer.restored', 'customer.group.assigned', 'customer.status.changed', 'customer.loyalty.points_adjusted', 'customer.wallet.adjusted' => $event->aggregateId() ? route('customers.show', $event->aggregateId()) : route('customers.index'),
            'customer.birthday.upcoming', 'customer.birthday.today' => route('customers.birthdays.index'),
            'customer.inactive.detected' => route('customers.inactive.index'),
            'customer.lost.detected' => route('customers.lost.index'),
            'customer.frequent_returner.detected' => route('customers.returns.index'),
            default => route('notifications.index'),
        };
    }
}
