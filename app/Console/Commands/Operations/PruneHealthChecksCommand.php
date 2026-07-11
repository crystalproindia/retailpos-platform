<?php

namespace App\Console\Commands\Operations;

use App\Models\ScheduledTaskRun;
use App\Repositories\Operations\HealthCheckRepository;
use App\Repositories\Operations\QueueSnapshotRepository;
use App\Repositories\Operations\ScheduledTaskRunRepository;
use Illuminate\Console\Command;
use Throwable;

class PruneHealthChecksCommand extends Command
{
    protected $signature = 'operations:prune-health-checks';

    protected $description = 'Prune old operations monitor snapshots.';

    public function handle(
        HealthCheckRepository $healthChecks,
        QueueSnapshotRepository $queueSnapshots,
        ScheduledTaskRunRepository $scheduledTaskRuns,
    ): int {
        $startedAt = now();
        $run = ScheduledTaskRun::create([
            'command' => $this->signature,
            'description' => $this->description,
            'status' => 'running',
            'started_at' => $startedAt,
        ]);

        try {
            $healthRetention = (int) config('operations.health_retention_days', 30);
            $snapshotRetention = (int) config('operations.snapshot_retention_days', 30);
            $deletedHealth = $healthChecks->prune($healthRetention);
            $deletedSnapshots = $queueSnapshots->prune($snapshotRetention);
            $deletedRuns = $scheduledTaskRuns->prune($snapshotRetention);

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
                'output' => "Pruned {$deletedHealth} health checks, {$deletedSnapshots} queue snapshots, and {$deletedRuns} task runs.",
            ]);

            $this->info($run->output);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
                'failure_reason' => str($exception->getMessage())->limit(1000)->toString(),
            ]);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
