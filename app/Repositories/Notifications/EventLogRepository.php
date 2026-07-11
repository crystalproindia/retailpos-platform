<?php

namespace App\Repositories\Notifications;

use App\Models\DomainEventLog;
use App\Models\User;
use App\Support\Events\EventCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EventLogRepository
{
    public function __construct(private readonly EventCatalog $eventCatalog) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        return DomainEventLog::query()
            ->with('user')
            ->where('company_id', $user->company_id)
            ->when($filters['event_key'] ?? null, fn ($query, string $eventKey) => $query->where('event_key', $eventKey))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['actor'] ?? null, fn ($query, int|string $actor) => $query->where('user_id', $actor))
            ->when($filters['aggregate_type'] ?? null, fn ($query, string $aggregateType) => $query->where('aggregate_type', $aggregateType))
            ->latest('occurred_at')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return array<string, string>
     */
    public function eventOptions(): array
    {
        return $this->eventCatalog->all()
            ->mapWithKeys(fn (array $definition, string $key): array => [$key => $definition['name']])
            ->all();
    }
}
