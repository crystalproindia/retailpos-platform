<?php

namespace App\Console\Commands\Operations;

use App\Models\ScheduledTaskRun;
use App\Services\Operations\QueueMonitorService;
use Illuminate\Console\Command;
use Throwable;

class CaptureQueueSnapshotCommand extends Command
{
    protected $signature = 'operations:capture-queue-snapshot {--queue=}';

    protected $description = 'Capture queue and failed job statistics.';

    public function handle(QueueMonitorService $queueMonitorService): int
    {
        $startedAt = now();
        $run = ScheduledTaskRun::create([
            'command' => 'operations:capture-queue-snapshot',
            'description' => $this->description,
            'status' => 'running',
            'started_at' => $startedAt,
        ]);

        try {
            $snapshot = $queueMonitorService->captureSnapshot($this->option('queue') ?: null);

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
                'output' => "Queue {$snapshot->queue}: {$snapshot->pending_count} pending, {$snapshot->failed_count} failed.",
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
