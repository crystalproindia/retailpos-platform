<?php

namespace App\Console\Commands\RetailPos;

use App\Models\Company;
use App\Jobs\Ai\RefreshAiForecastsJob;
use Illuminate\Console\Command;

class RefreshAiForecastsCommand extends Command
{
    protected $signature = 'retailpos:refresh-ai-forecasts {--company= : A single company ID} {--type=all : sales, inventory, customers, crm, or all}';
    protected $description = 'Generate tenant-scoped, deterministic advisory forecasts and insights.';

    public function handle(): int
    {
        if (! config('ai_forecasting.scheduled_generation_enabled')) { $this->info('Scheduled AI forecasting is disabled.'); return self::SUCCESS; }
        $type = (string) $this->option('type');
        if (! in_array($type, ['all', 'sales', 'inventory', 'customers', 'crm'], true)) { $this->error('Unsupported forecast type.'); return self::INVALID; }
        $companies = Company::query()->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))->orderBy('id')->get();
        foreach ($companies as $company) RefreshAiForecastsJob::dispatch($company->id, $type);
        $this->info('AI forecast refresh queued for '.$companies->count().' company account(s).');
        return self::SUCCESS;
    }
}
