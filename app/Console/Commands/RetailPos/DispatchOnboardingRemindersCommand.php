<?php

namespace App\Console\Commands\RetailPos;

use App\Enums\Crm\OnboardingDocumentStatus;
use App\Enums\Crm\OnboardingStatus;
use App\Enums\Crm\OnboardingTaskStatus;
use App\Events\Domain\Crm\OnboardingEvent;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Console\Command;

class DispatchOnboardingRemindersCommand extends Command
{
    protected $signature = 'retailpos:onboarding-reminders';

    protected $description = 'Dispatch idempotent reminders for overdue onboarding work and pending documents.';

    public function handle(DomainEventDispatcher $events): int
    {
        $count = 0;

        CrmCustomerOnboarding::query()
            ->with(['customer', 'tasks', 'documents'])
            ->whereNotIn('status', [OnboardingStatus::Live->value, OnboardingStatus::Cancelled->value])
            ->orderBy('id')
            ->chunkById(100, function ($onboardings) use ($events, &$count): void {
                foreach ($onboardings as $onboarding) {
                    $payload = $this->payload($onboarding);

                    if ($onboarding->target_go_live_date?->lt(today())) {
                        $events->dispatch(new OnboardingEvent('crm.onboarding.target_go_live_missed', $onboarding->company_id, null, CrmCustomerOnboarding::class, $onboarding->id, $payload, 'crm.onboarding.target_go_live_missed:'.$onboarding->id.':'.$onboarding->target_go_live_date->toDateString()));
                        $count++;
                    }

                    foreach ($onboarding->tasks->filter(fn ($task): bool => $task->due_date && $task->due_date->lt(today()) && ! in_array($task->status, [OnboardingTaskStatus::Completed, OnboardingTaskStatus::Skipped], true)) as $task) {
                        $events->dispatch(new OnboardingEvent('crm.onboarding.task_overdue', $onboarding->company_id, null, CrmCustomerOnboarding::class, $onboarding->id, $payload + ['task_id' => $task->id, 'task_title' => $task->title], 'crm.onboarding.task_overdue:'.$task->id.':'.$task->due_date->toDateString()));
                        $count++;
                    }

                    foreach ($onboarding->documents->where('status', OnboardingDocumentStatus::Requested) as $document) {
                        $events->dispatch(new OnboardingEvent('crm.onboarding.document_pending', $onboarding->company_id, null, CrmCustomerOnboarding::class, $onboarding->id, $payload + ['document_id' => $document->id, 'document_title' => $document->title], 'crm.onboarding.document_pending:'.$document->id.':'.now()->toDateString()));
                        $count++;
                    }
                }
            });

        $this->info("Dispatched {$count} onboarding reminder checks.");

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function payload(CrmCustomerOnboarding $onboarding): array
    {
        return [
            'onboarding_id' => $onboarding->id,
            'onboarding_number' => $onboarding->onboarding_number,
            'customer_name' => $onboarding->business_name ?: $onboarding->customer?->display_name,
            'assigned_user_id' => $onboarding->assigned_to,
            'implementation_owner_id' => $onboarding->implementation_owner_id,
            'target_go_live_date' => $onboarding->target_go_live_date?->toDateString(),
        ];
    }
}
