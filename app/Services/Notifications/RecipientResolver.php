<?php

namespace App\Services\Notifications;

use App\Contracts\Events\DomainEvent;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class RecipientResolver
{
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

        $users = match ($event->eventKey()) {
            'pos.sale.held', 'pos.sale.completed', 'pos.offline.bill.queued', 'pos.offline.sync.started', 'pos.offline.sync.completed', 'pos.offline.sync.failed', 'pos.offline.sync.record_failed', 'pos.offline.sync.warning' => $this->managers($event->companyId()),
            'crm.lead.assigned' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null]),
            'crm.follow_up.due', 'crm.follow_up.overdue' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null]),
            'crm.lead.created' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null])
                ->merge($this->managers($event->companyId())),
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
}
