<?php

namespace App\Console\Commands\Operations;

use App\Models\ScheduledTaskRun;
use App\Services\Operations\HealthCheckService;
use Illuminate\Console\Command;
use Throwable;

class RunHealthCheckCommand extends Command
{
    protected $signature = 'operations:health-check';

    protected $description = 'Run operations health checks and store snapshots.';

    public function handle(HealthCheckService $healthCheckService): int
    {
        $startedAt = now();
        $run = ScheduledTaskRun::create([
            'command' => $this->signature,
            'description' => $this->description,
            'status' => 'running',
            'started_at' => $startedAt,
        ]);

        try {
            $checks = $healthCheckService->runAll();
            $status = $healthCheckService->overallStatus($checks);

            $run->update([
                'status' => $status === 'critical' ? 'warning' : 'success',
                'finished_at' => now(),
                'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
                'output' => "Stored {$checks->count()} health checks. Overall status: {$status}.",
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
