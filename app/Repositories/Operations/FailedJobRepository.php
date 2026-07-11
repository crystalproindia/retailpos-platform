<?php

namespace App\Repositories\Operations;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FailedJobRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return DB::table('failed_jobs')
            ->when($filters['queue'] ?? null, fn ($query, string $queue) => $query->where('queue', $queue))
            ->when($filters['connection'] ?? null, fn ($query, string $connection) => $query->where('connection', $connection))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('uuid', 'like', "%{$search}%")
                        ->orWhere('queue', 'like', "%{$search}%")
                        ->orWhere('payload', 'like', "%{$search}%")
                        ->orWhere('exception', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('failed_at')
            ->paginate(15)
            ->withQueryString();
    }

    public function find(int $id): object
    {
        return DB::table('failed_jobs')->where('id', $id)->firstOrFail();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, object>
     */
    public function findMany(array $ids): Collection
    {
        return DB::table('failed_jobs')
            ->whereIn('id', $ids)
            ->get();
    }

    public function delete(int $id): int
    {
        return DB::table('failed_jobs')->where('id', $id)->delete();
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function deleteMany(array $ids): int
    {
        return DB::table('failed_jobs')->whereIn('id', $ids)->delete();
    }

    /**
     * @return Collection<int, string>
     */
    public function queues(): Collection
    {
        return DB::table('failed_jobs')
            ->select('queue')
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue');
    }

    public function count(): int
    {
        return DB::table('failed_jobs')->count();
    }
}
