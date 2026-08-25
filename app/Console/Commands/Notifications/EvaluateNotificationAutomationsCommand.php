<?php

namespace App\Console\Commands\Notifications;

use App\Models\Company;
use App\Services\Notifications\NotificationAutomationEvaluator;
use Illuminate\Console\Command;
use Throwable;

class EvaluateNotificationAutomationsCommand extends Command
{
    protected $signature = 'notifications:evaluate-automations {--company= : Evaluate one company ID} {--limit=100 : Maximum companies per run}';

    protected $description = 'Evaluate tenant notification automations without duplicating active alerts.';

    public function handle(NotificationAutomationEvaluator $evaluator): int
    {
        $query = Company::query()->where('is_active', true)->orderBy('id');
        if ($this->option('company')) {
            $query->whereKey((int) $this->option('company'));
        }

        $failed = 0;
        $query->limit(max(1, min(500, (int) $this->option('limit'))))->get()->each(function (Company $company) use ($evaluator, &$failed): void {
            try {
                $results = $evaluator->evaluate($company);
                $created = collect($results)->sum('created');
                $this->line($company->name.': '.$created.' new in-app notification(s).');
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $this->error($company->name.': evaluation failed safely.');
            }
        });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
