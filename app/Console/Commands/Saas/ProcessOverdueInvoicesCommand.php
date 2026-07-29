<?php

namespace App\Console\Commands\Saas;

use App\Services\Saas\SaasBillingOperationsService;
use Illuminate\Console\Command;

class ProcessOverdueInvoicesCommand extends Command
{
    protected $signature = 'saas:process-overdue-invoices {--dry-run}';
    protected $description = 'Mark overdue subscription invoices and apply the existing past-due lifecycle transition.';
    public function handle(SaasBillingOperationsService $billing): int { $r = $billing->processOverdue((bool) $this->option('dry-run')); $this->table(['Inspected', 'Overdue', 'Transitions'], [[$r['inspected'], $r['overdue'], $r['transitions']]]); return self::SUCCESS; }
}
