<?php

namespace App\Console\Commands;

use App\Services\Tasks\TaskReminderService;
use Illuminate\Console\Command;

class SendSmartTaskReminders extends Command
{
    protected $signature = 'tasks:send-reminders {--company= : Limit reminder delivery to one tenant company} {--limit=250 : Maximum tasks evaluated} {--dry-run : Report reminder candidates without sending}';

    protected $description = 'Send idempotent in-app and preference-aware task reminders';

    public function handle(TaskReminderService $reminders): int
    {
        $counts = $reminders->deliverDueReminders(
            companyId: $this->option('company') ? (int) $this->option('company') : null,
            limit: max(1, min((int) $this->option('limit'), 1000)),
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->table(array_keys($counts), [array_values($counts)]);

        return self::SUCCESS;
    }
}
