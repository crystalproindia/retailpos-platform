<?php

namespace App\Console\Commands\Saas;

use App\Models\Company;
use App\Services\Saas\UsageService;
use Illuminate\Console\Command;

class RecalculateUsageCommand extends Command
{
    protected $signature = 'saas:recalculate-usage {--company= : Company ID to process} {--dry-run : Report current usage without saving snapshots}';
    protected $description = 'Recalculate SaaS usage from source records for one tenant or all tenants.';
    public function handle(UsageService $usage): int { $query = Company::query()->orderBy('id'); if ($id = $this->option('company')) $query->whereKey($id); $count=0; $alerts=0; $query->each(function (Company $company) use ($usage, &$count, &$alerts): void { $summary=$usage->recalculate($company, ! $this->option('dry-run')); $count++; $alerts += collect($summary)->whereIn('state',['near_limit','exceeded'])->count(); }); $this->info("Processed {$count} tenant(s); {$alerts} usage alert(s)."); return self::SUCCESS; }
}
