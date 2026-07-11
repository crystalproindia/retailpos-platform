<?php

namespace App\Repositories\Notifications;

use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeliveryLogRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        return NotificationDelivery::query()
            ->with(['eventLog', 'user'])
            ->where('company_id', $user->company_id)
            ->when($filters['channel'] ?? null, fn ($query, string $channel) => $query->where('channel', $channel))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['event_key'] ?? null, fn ($query, string $eventKey) => $query->where('event_key', $eventKey))
            ->when($filters['user_id'] ?? null, fn ($query, int|string $userId) => $query->where('user_id', $userId))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }
}
