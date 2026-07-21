<?php

namespace App\Console\Commands\Saas;

use App\Services\Saas\SubscriptionBackfillService;
use Illuminate\Console\Command;

class BackfillSubscriptionsCommand extends Command
{
    protected $signature = 'saas:backfill-subscriptions {--dry-run : Report subscriptions that would be created without writing}';
    protected $description = 'Create missing complimentary grandfathered subscriptions for existing tenants.';
    public function handle(SubscriptionBackfillService $backfill): int { $result = $backfill->run((bool) $this->option('dry-run')); $this->table(['Inspected','Created','Skipped','Warnings','Failures'], [[$result['inspected'],$result['created'],$result['skipped'],count($result['warnings']),count($result['failures'])]]); foreach ($result['failures'] as $failure) $this->error($failure); return $result['failures'] === [] ? self::SUCCESS : self::FAILURE; }
}
