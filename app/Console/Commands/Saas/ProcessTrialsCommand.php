<?php

namespace App\Console\Commands\Saas;

use App\Services\Saas\SaasLifecycleService;
use Illuminate\Console\Command;

class ProcessTrialsCommand extends Command
{
    protected $signature = 'saas:process-trials {--dry-run : Report lifecycle work without changing subscriptions}';
    protected $description = 'Send idempotent trial reminders and move ended trials into grace or expired status.';
    public function handle(SaasLifecycleService $lifecycle): int { $result = $lifecycle->processTrials((bool) $this->option('dry-run')); $this->table(['Trials inspected', 'Reminders', 'Transitions'], [[$result['trials'], $result['reminders'], $result['transitions']]]); return self::SUCCESS; }
}
