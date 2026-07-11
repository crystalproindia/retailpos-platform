<?php

namespace App\Repositories\Notifications;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

class NotificationInboxRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        return $user->notifications()
            ->when(($filters['status'] ?? null) === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when(($filters['status'] ?? null) === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('data->title', 'like', "%{$search}%")
                        ->orWhere('data->message', 'like', "%{$search}%")
                        ->orWhere('data->event_key', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function findForUser(User $user, string $notificationId): DatabaseNotification
    {
        return $user->notifications()->whereKey($notificationId)->firstOrFail();
    }
}
