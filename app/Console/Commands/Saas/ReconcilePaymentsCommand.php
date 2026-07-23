<?php

namespace App\Console\Commands\Saas;

use App\Services\Saas\SaasBillingOperationsService;
use Illuminate\Console\Command;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'saas:reconcile-payments {--dry-run} {--company=}';
    protected $description = 'Reconcile subscription payment records against their invoices.';
    public function handle(SaasBillingOperationsService $billing): int { $r = $billing->reconcile((bool) $this->option('dry-run'), $this->option('company') ? (int) $this->option('company') : null); $this->table(['Inspected', 'Matched', 'Exceptions'], [[$r['inspected'], $r['matched'], $r['exceptions']]]); return self::SUCCESS; }
}
