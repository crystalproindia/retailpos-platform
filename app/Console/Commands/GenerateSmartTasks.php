<?php

namespace App\Console\Commands;

use App\Services\Tasks\TaskRuleService;
use Illuminate\Console\Command;

class GenerateSmartTasks extends Command
{
    protected $signature = 'tasks:generate {--company= : Limit generation to one tenant company} {--limit=250 : Maximum source records evaluated per rule} {--dry-run : Report eligible records without creating tasks}';

    protected $description = 'Generate bounded, idempotent smart work tasks from enabled rules';

    public function handle(TaskRuleService $rules): int
    {
        $counts = $rules->generate(
            companyId: $this->option('company') ? (int) $this->option('company') : null,
            limit: max(1, min((int) $this->option('limit'), 1000)),
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->table(array_keys($counts), [array_values($counts)]);

        return self::SUCCESS;
    }
}
