<?php

namespace App\Console\Commands\Notifications;

use App\Models\DomainEventLog;
use Illuminate\Console\Command;

class PruneDomainEventLogsCommand extends Command
{
    protected $signature = 'notifications:prune-domain-events';

    protected $description = 'Prune domain event logs beyond the configured retention period.';

    public function handle(): int
    {
        $retentionDays = (int) config('events.retention_days', 180);

        $deleted = DomainEventLog::query()
            ->where('occurred_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->info("Pruned {$deleted} domain event logs older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
