<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;

class SubscriptionBackfillService
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /** @return array{inspected:int, created:int, skipped:int, warnings:array<int,string>, failures:array<int,string>} */
    public function run(bool $dryRun = true): array
    {
        $result = ['inspected' => 0, 'created' => 0, 'skipped' => 0, 'warnings' => [], 'failures' => []];
        $plan = SaasPlan::query()->where('code', config('saas.grandfathered_plan_code'))->first();
        if (! $plan) { $result['failures'][] = 'Grandfathered plan is missing.'; return $result; }
        Company::query()->orderBy('id')->chunkById(100, function ($companies) use (&$result, $plan, $dryRun): void {
            foreach ($companies as $company) {
                $result['inspected']++;
                if (SaasSubscription::query()->where('company_id', $company->id)->whereIn('status', ['trialing','active','grace_period','past_due','suspended'])->exists()) { $result['skipped']++; continue; }
                if ($dryRun) { $result['created']++; continue; }
                try { $this->subscriptions->create($company, $plan, null, 'complimentary'); $result['created']++; }
                catch (\Throwable $exception) { $result['failures'][] = "Company {$company->id}: {$exception->getMessage()}"; }
            }
        });
        return $result;
    }
}
