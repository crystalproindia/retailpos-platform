<?php

namespace App\Services\Operations;

use App\Models\QueueJobSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueMonitorService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $connection = config('queue.default');

        return [
            'connection' => $connection,
            'driver' => config("queue.connections.{$connection}.driver", 'unknown'),
            'default_queue' => config("queue.connections.{$connection}.queue", 'default'),
            'pending_count' => $this->pendingCount(),
            'reserved_count' => $this->reservedCount(),
            'failed_count' => $this->failedCount(),
        ];
    }

    public function captureSnapshot(?string $queue = null): QueueJobSnapshot
    {
        $queue ??= (string) config('queue.connections.'.config('queue.default').'.queue', 'default');

        return QueueJobSnapshot::create([
            'queue' => $queue,
            'pending_count' => $this->pendingCount($queue),
            'failed_count' => $this->failedCount($queue),
            'processed_count' => null,
            'reserved_count' => $this->reservedCount($queue),
            'captured_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function queueBreakdown(): Collection
    {
        if (! Schema::hasTable('jobs')) {
            return collect();
        }

        $jobs = DB::table('jobs')
            ->select('queue')
            ->selectRaw('count(*) as total_count')
            ->selectRaw('sum(case when reserved_at is null then 1 else 0 end) as pending_count')
            ->selectRaw('sum(case when reserved_at is not null then 1 else 0 end) as reserved_count')
            ->groupBy('queue')
            ->orderBy('queue')
            ->get();

        $failedByQueue = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->select('queue')->selectRaw('count(*) as failed_count')->groupBy('queue')->pluck('failed_count', 'queue')
            : collect();

        return $jobs->map(fn (object $job): array => [
            'queue' => $job->queue,
            'pending_count' => (int) $job->pending_count,
            'reserved_count' => (int) $job->reserved_count,
            'failed_count' => (int) ($failedByQueue[$job->queue] ?? 0),
            'total_count' => (int) $job->total_count,
        ])->values();
    }

    private function pendingCount(?string $queue = null): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        return DB::table('jobs')
            ->when($queue, fn ($query, string $queue) => $query->where('queue', $queue))
            ->whereNull('reserved_at')
            ->count();
    }

    private function reservedCount(?string $queue = null): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        return DB::table('jobs')
            ->when($queue, fn ($query, string $queue) => $query->where('queue', $queue))
            ->whereNotNull('reserved_at')
            ->count();
    }

    private function failedCount(?string $queue = null): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')
            ->when($queue, fn ($query, string $queue) => $query->where('queue', $queue))
            ->count();
    }
}
