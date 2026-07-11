<?php

namespace App\Repositories\Notifications;

use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WebhookRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateEndpoints(User $user, array $filters = []): LengthAwarePaginator
    {
        return WebhookEndpoint::query()
            ->where('company_id', $user->company_id)
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when(($filters['status'] ?? null) === 'disabled', fn ($query) => $query->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function findEndpointForUser(User $user, int $endpointId, bool $withTrashed = false): WebhookEndpoint
    {
        return WebhookEndpoint::query()
            ->where('company_id', $user->company_id)
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->findOrFail($endpointId);
    }

    public function findDeliveryForUser(User $user, int $deliveryId): WebhookDelivery
    {
        return WebhookDelivery::query()
            ->with('endpoint')
            ->where('company_id', $user->company_id)
            ->findOrFail($deliveryId);
    }

    public function paginateDeliveries(User $user): LengthAwarePaginator
    {
        return WebhookDelivery::query()
            ->with('endpoint')
            ->where('company_id', $user->company_id)
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }
}
