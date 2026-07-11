<?php

namespace App\Services\Operations;

use App\Repositories\Operations\ScheduledTaskRunRepository;
use Illuminate\Support\Collection;

class ScheduleMonitorService
{
    public function __construct(private readonly ScheduledTaskRunRepository $scheduledTaskRuns) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function tasks(): Collection
    {
        $latestRuns = $this->scheduledTaskRuns->latestByCommand();

        return collect(config('operations.scheduled_commands', []))
            ->map(function (array $definition, string $command) use ($latestRuns): array {
                $latest = $latestRuns->get($command);

                return [
                    'command' => $command,
                    'description' => $definition['description'] ?? null,
                    'frequency' => $definition['frequency'] ?? 'Configured',
                    'next_run' => $this->nextRun($definition['frequency'] ?? ''),
                    'last_run' => $latest,
                    'status' => $latest?->status ?? 'unknown',
                    'failure_reason' => $latest?->failure_reason,
                ];
            })
            ->values();
    }

    private function nextRun(string $frequency): ?string
    {
        $now = now();

        return match ($frequency) {
            'Every 5 minutes' => $now->copy()->addMinutes(5 - ((int) $now->format('i') % 5))->startOfMinute()->format('Y-m-d H:i T'),
            'Every 15 minutes' => $now->copy()->addMinutes(15 - ((int) $now->format('i') % 15))->startOfMinute()->format('Y-m-d H:i T'),
            'Hourly' => $now->copy()->addHour()->startOfHour()->format('Y-m-d H:i T'),
            'Daily at 02:30' => $this->dailyAt('02:30'),
            'Daily at 03:00' => $this->dailyAt('03:00'),
            default => null,
        };
    }

    private function dailyAt(string $time): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $next = now()->copy()->setTime($hour, $minute);

        if ($next->isPast()) {
            $next->addDay();
        }

        return $next->format('Y-m-d H:i T');
    }
}
