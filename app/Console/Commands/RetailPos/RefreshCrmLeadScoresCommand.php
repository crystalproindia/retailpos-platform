<?php

namespace App\Console\Commands\RetailPos;

use App\Models\Crm\CrmLead;
use App\Services\Crm\CrmLeadScoringService;
use Illuminate\Console\Command;

class RefreshCrmLeadScoresCommand extends Command
{
    protected $signature = 'retailpos:crm-refresh-lead-scores {--lead= : Refresh a single lead ID} {--all : Refresh every active lead} {--stale : Refresh leads whose score is missing or older than one day}';

    protected $description = 'Refresh rule-based CRM lead score snapshots.';

    public function handle(CrmLeadScoringService $scoring): int
    {
        $query = CrmLead::query()->whereNull('deleted_at');
        if ($leadId = $this->option('lead')) $query->whereKey($leadId);
        elseif ($this->option('stale')) $query->where(fn ($query) => $query->whereDoesntHave('leadScore')->orWhereHas('leadScore', fn ($score) => $score->where('analyzed_at', '<', now()->subDay())));
        elseif (! $this->option('all')) {
            $this->error('Choose --lead, --all, or --stale.');
            return self::INVALID;
        }

        $count = 0;
        $query->orderBy('id')->each(function (CrmLead $lead) use ($scoring, &$count): void {
            $scoring->refresh($lead);
            $count++;
        });
        $this->info("Refreshed {$count} CRM lead score(s).");

        return self::SUCCESS;
    }
}
