<?php

namespace App\Console\Commands\Saas;

use App\Services\Saas\SaasLifecycleService;
use Illuminate\Console\Command;

class ProcessExpirationsCommand extends Command
{
    protected $signature = 'saas:process-expirations {--dry-run : Report expiration work without changing subscriptions}';
    protected $description = 'Suspend subscriptions whose grace period has elapsed.';
    public function handle(SaasLifecycleService $lifecycle): int { $result = $lifecycle->processExpirations((bool) $this->option('dry-run')); $this->table(['Subscriptions inspected', 'Suspensions'], [[$result['inspected'], $result['transitions']]]); return self::SUCCESS; }
}
