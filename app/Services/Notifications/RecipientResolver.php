<?php

namespace App\Services\Notifications;

use App\Contracts\Events\DomainEvent;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class RecipientResolver
{
    public function __construct(private readonly LeadNotificationSettings $settings) {}

    /**
     * @return Collection<int, User>
     */
    public function resolve(DomainEvent $event): Collection
    {
        if (! $event->companyId()) {
            return collect();
        }

        $payload = $event->payload();

        $query = User::query()
            ->where('company_id', $event->companyId())
            ->where('is_active', true);

        if (in_array($event->eventKey(), ['crm.lead.created', 'crm.lead.assigned', 'crm.lead.status_changed', 'crm.pipeline.stage_changed', 'crm.lead_score.hot', 'crm.lead_score.at_risk', 'crm.lead_followup_overdue', 'crm.payment_followup_required', 'crm.demo.scheduled', 'crm.demo.google_calendar_synced', 'crm.demo.google_calendar_sync_failed', 'crm.quotation.created', 'crm.quotation.sent', 'crm.quotation.accepted', 'crm.quotation.rejected', 'crm.proforma.created', 'crm.proforma.sent', 'crm.proforma.payment_recorded', 'crm.proforma.fully_paid', 'crm.proforma.share_failed', 'crm.customer.created'], true)
            && ! $this->settings->leadAlertsEnabled($event->companyId())) {
            return collect();
        }

        if (in_array($event->eventKey(), ['crm.follow_up.due', 'crm.follow_up.overdue'], true)
            && ! $this->settings->followUpRemindersEnabled($event->companyId())) {
            return collect();
        }

        $users = match ($event->eventKey()) {
            'pos.sale.held', 'pos.sale.completed', 'pos.offline.bill.queued', 'pos.offline.sync.started', 'pos.offline.sync.completed', 'pos.offline.sync.failed', 'pos.offline.sync.record_failed', 'pos.offline.sync.warning' => $this->managers($event->companyId()),
            'crm.lead.assigned' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null]),
            'crm.demo.scheduled', 'crm.demo.google_calendar_synced', 'crm.demo.google_calendar_sync_failed', 'crm.quotation.created', 'crm.quotation.sent', 'crm.quotation.accepted', 'crm.quotation.rejected', 'crm.proforma.created', 'crm.proforma.sent', 'crm.proforma.payment_recorded', 'crm.proforma.fully_paid', 'crm.proforma.share_failed', 'crm.customer.created' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null])
                ->merge($this->managers($event->companyId())),
            'crm.lead.status_changed' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null])
                ->merge($this->managers($event->companyId())),
            'crm.pipeline.stage_changed' => ($payload['notify'] ?? false)
                ? $this->usersByIds($query, [$payload['assigned_user_id'] ?? null])->merge($this->managers($event->companyId()))
                : collect(),
            'crm.lead_score.hot', 'crm.lead_score.at_risk', 'crm.lead_followup_overdue', 'crm.payment_followup_required' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null])
                ->merge($this->managers($event->companyId())),
            'crm.onboarding.started', 'crm.onboarding.task_assigned', 'crm.onboarding.task_overdue', 'crm.onboarding.target_go_live_missed', 'crm.onboarding.document_pending', 'crm.onboarding.blocked', 'crm.onboarding.go_live_ready', 'crm.onboarding.live', 'crm.onboarding.on_hold', 'crm.onboarding.cancelled' => $this->usersByIds($query, [$payload['implementation_owner_id'] ?? null, $payload['assigned_user_id'] ?? null])
                ->merge($this->managers($event->companyId())),
            'crm.support_ticket_created', 'crm.support_ticket_assigned', 'crm.support_ticket_urgent', 'crm.support_ticket_overdue', 'crm.support_ticket_resolved', 'crm.support_ticket_reopened', 'crm.support_ticket_waiting_internal', 'crm.support_ticket_status_changed' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null])
                ->merge($this->managers($event->companyId())),
            'crm.follow_up.due', 'crm.follow_up.overdue' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null])
                ->merge($this->managers($event->companyId())),
            'crm.lead.created' => $this->leadCreatedRecipients($event, $query),
            'cms.page.published', 'cms.page.unpublished', 'cms.media.uploaded', 'cms.branding.updated', 'cms.theme.updated', 'cms.client_logo.created', 'cms.client_logo.updated', 'cms.case_study.created', 'cms.case_study.published', 'cms.case_study.unpublished', 'cms.testimonial.created', 'cms.trust_metric.updated', 'cms.cta.updated', 'cms.seo.updated', 'system.settings.updated' => $this->managers($event->companyId()),
            'inventory.stock.low',
            'inventory.stock.out',
            'inventory.reorder.suggested',
            'inventory.channel.sync_warning' => $this->managers($event->companyId()),
            'purchase.request.submitted',
            'purchase.order.submitted',
            'purchase.goods_received',
            'purchase.return.created',
            'purchase.return.completed',
            'purchase.reorder_request.created',
            'purchase.supplier.score_updated' => $this->managers($event->companyId()),
            'promotion.campaign.created',
            'promotion.campaign.updated',
            'promotion.rule.created',
            'promotion.rule.updated',
            'promotion.rule.activated',
            'promotion.rule.paused',
            'promotion.rule.expired',
            'promotion.coupon.created',
            'promotion.coupon.used',
            'promotion.simulation.ran',
            'promotion.approval.required' => $this->managers($event->companyId()),
            'customer.created',
            'customer.updated',
            'customer.deleted',
            'customer.restored',
            'customer.group.assigned',
            'customer.status.changed',
            'customer.birthday.upcoming',
            'customer.birthday.today',
            'customer.inactive.detected',
            'customer.lost.detected',
            'customer.frequent_returner.detected',
            'customer.loyalty.points_adjusted',
            'customer.wallet.adjusted' => $this->managers($event->companyId()),
            default => $this->managers($event->companyId()),
        };

        return $users->filter()->unique('id')->values();
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return Collection<int, User>
     */
    private function usersByIds($query, array $ids): Collection
    {
        $ids = collect($ids)->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return (clone $query)->whereIn('id', $ids)->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function managers(int $companyId): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Administrator->value, UserRole::Manager->value])
            ->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return Collection<int, User>
     */
    private function leadCreatedRecipients(DomainEvent $event, $query): Collection
    {
        $payload = $event->payload();

        if (($payload['channel'] ?? null) !== 'public_website') {
            return $this->usersByIds($query, [$payload['assigned_user_id'] ?? null])
                ->merge($this->managers($event->companyId()));
        }

        $recipients = collect();

        if ($this->settings->notifyAdministrators($event->companyId())) {
            $recipients = $recipients->merge($this->managers($event->companyId()));
        }

        if (! $this->settings->notifySales($event->companyId())) {
            return $recipients;
        }

        $assignedSalesUser = $this->usersByIds($query, [$payload['assigned_user_id'] ?? null])
            ->filter(fn (User $user): bool => ($user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role)) === UserRole::Sales);

        return $recipients->merge($assignedSalesUser->isNotEmpty()
            ? $assignedSalesUser
            : $this->salesUsers($event->companyId()));
    }

    /**
     * @return Collection<int, User>
     */
    private function salesUsers(int $companyId): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('role', UserRole::Sales->value)
            ->get();
    }
}
