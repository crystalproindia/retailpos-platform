<?php

namespace App\Console\Commands\Notifications;

use App\Events\Domain\Crm\FollowUpOverdue;
use App\Models\Crm\CrmActivity;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Console\Command;

class DispatchOverdueFollowUpRemindersCommand extends Command
{
    protected $signature = 'notifications:dispatch-followup-overdue';

    protected $description = 'Dispatch idempotent notifications for overdue CRM follow-ups.';

    public function handle(DomainEventDispatcher $domainEvents): int
    {
        $count = 0;

        CrmActivity::query()
            ->with(['lead', 'assignedUser'])
            ->whereNull('completed_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', now())
            ->orderBy('scheduled_at')
            ->chunkById(100, function ($activities) use ($domainEvents, &$count): void {
                foreach ($activities as $activity) {
                    $domainEvents->dispatch(new FollowUpOverdue(
                        companyId: $activity->company_id,
                        actorId: null,
                        aggregateType: CrmActivity::class,
                        aggregateId: $activity->id,
                        payload: $this->payload($activity),
                        correlationId: 'crm.follow_up.overdue:'.$activity->id,
                    ));
                    $count++;
                }
            });

        $this->info("Dispatched {$count} overdue follow-up reminder checks.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CrmActivity $activity): array
    {
        return [
            'activity_id' => $activity->id,
            'subject' => $activity->subject,
            'scheduled_at' => $activity->scheduled_at?->toISOString(),
            'assigned_user_id' => $activity->assigned_user_id,
            'lead_id' => $activity->crm_lead_id,
            'lead_title' => $activity->lead?->title,
            'priority' => $activity->priority?->value ?? $activity->priority,
        ];
    }
}
