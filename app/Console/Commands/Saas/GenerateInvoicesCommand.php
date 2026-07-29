<?php

namespace App\Console\Commands\Saas;

use App\Services\Saas\SaasBillingOperationsService;
use Illuminate\Console\Command;

class GenerateInvoicesCommand extends Command
{
    protected $signature = 'saas:generate-invoices {--dry-run} {--company=} {--subscription=} {--date=}';
    protected $description = 'Generate idempotent advance subscription invoices.';
    public function handle(SaasBillingOperationsService $billing): int { $r = $billing->generateInvoices((bool) $this->option('dry-run'), $this->option('company') ? (int) $this->option('company') : null, $this->option('subscription') ? (int) $this->option('subscription') : null, $this->option('date')); $this->table(['Inspected', 'Created', 'Skipped'], [[$r['inspected'], $r['created'], $r['skipped']]]); return self::SUCCESS; }
}
