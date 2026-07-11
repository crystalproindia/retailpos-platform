<?php

namespace App\Repositories\Operations;

use App\Models\SystemHealthCheck;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class HealthCheckRepository
{
    /**
     * @return Collection<string, SystemHealthCheck>
     */
    public function latestByKey(): Collection
    {
        return SystemHealthCheck::query()
            ->latest('checked_at')
            ->get()
            ->unique('key')
            ->keyBy('key');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return SystemHealthCheck::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('key', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->latest('checked_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function prune(int $retentionDays): int
    {
        return SystemHealthCheck::query()
            ->where('checked_at', '<', now()->subDays($retentionDays))
            ->delete();
    }
}
