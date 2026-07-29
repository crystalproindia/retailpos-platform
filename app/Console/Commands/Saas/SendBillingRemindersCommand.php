<?php

namespace App\Console\Commands\Saas;

use App\Services\Saas\SaasBillingOperationsService;
use Illuminate\Console\Command;

class SendBillingRemindersCommand extends Command
{
    protected $signature = 'saas:send-billing-reminders {--dry-run}';
    protected $description = 'Queue idempotent subscription invoice reminders through the existing email delivery system.';
    public function handle(SaasBillingOperationsService $billing): int { $r = $billing->sendReminders((bool) $this->option('dry-run')); $this->table(['Inspected', 'Reminders'], [[$r['inspected'], $r['reminders']]]); return self::SUCCESS; }
}
