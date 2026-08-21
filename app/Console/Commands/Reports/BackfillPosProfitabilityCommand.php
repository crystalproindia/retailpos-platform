<?php

namespace App\Console\Commands\Reports;

use App\Services\Reports\PosProfitabilityBackfillService;
use Illuminate\Console\Command;

class BackfillPosProfitabilityCommand extends Command
{
    protected $signature = 'reports:backfill-pos-profitability {--company= : Restrict to one company} {--after-id= : Resume after a sale-item ID} {--chunk=200 : Bounded processing chunk size} {--dry-run : Report only; do not write snapshots}';
    protected $description = 'Reconstruct only unambiguous historical POS cost snapshots from sale stock movements.';

    public function handle(PosProfitabilityBackfillService $backfill): int
    {
        $result = $backfill->run($this->option('company') ? (int) $this->option('company') : null, (bool) $this->option('dry-run'), max(1, (int) $this->option('chunk')), $this->option('after-id') ? (int) $this->option('after-id') : null);
        $this->table(['Inspected', 'Reconstructed', 'Unavailable', 'Last item ID'], [[$result['inspected'], $result['reconstructed'], $result['unavailable'], $result['last_id']]]);
        return self::SUCCESS;
    }
}
