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
            'crm.lead.assigned' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null]),
            'crm.follow_up.due', 'crm.follow_up.overdue' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null]),
            'crm.lead.created' => $this->usersByIds($query, [$payload['assigned_user_id'] ?? null])
                ->merge($this->managers($event->companyId())),
            'cms.page.published', 'cms.page.unpublished', 'cms.media.uploaded', 'system.settings.updated' => $this->managers($event->companyId()),
            'inventory.stock.low',
            'inventory.stock.out',
            'inventory.reorder.suggested',
            'inventory.channel.sync_warning' => $this->managers($event->companyId()),
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
