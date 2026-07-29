<?php

namespace App\Console\Commands\Saas;

use App\Services\Saas\SaasLifecycleService;
use Illuminate\Console\Command;

class ProcessRenewalsCommand extends Command
{
    protected $signature = 'saas:process-renewals {--dry-run : Report renewal work without changing subscriptions}';
    protected $description = 'Send idempotent renewal reminders and enter grace period after an unpaid renewal date.';
    public function handle(SaasLifecycleService $lifecycle): int { $result = $lifecycle->processRenewals((bool) $this->option('dry-run')); $this->table(['Subscriptions inspected', 'Reminders', 'Transitions'], [[$result['renewals'], $result['reminders'], $result['transitions']]]); return self::SUCCESS; }
}
