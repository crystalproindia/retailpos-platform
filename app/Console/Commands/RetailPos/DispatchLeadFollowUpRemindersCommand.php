<?php

namespace App\Console\Commands\RetailPos;

use App\Enums\Crm\LeadStageType;
use App\Events\Domain\Crm\FollowUpDue;
use App\Models\Crm\CrmLead;
use App\Services\Events\DomainEventDispatcher;
use App\Services\Notifications\LeadNotificationSettings;
use Illuminate\Console\Command;

class DispatchLeadFollowUpRemindersCommand extends Command
{
    protected $signature = 'retailpos:lead-followup-reminders';

    protected $description = 'Dispatch idempotent reminders for CRM leads with a due follow-up date.';

    public function handle(DomainEventDispatcher $domainEvents, LeadNotificationSettings $settings): int
    {
        $count = 0;

        CrmLead::query()
            ->with(['assignedUser', 'source', 'status'])
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now())
            ->whereHas('status', function ($query): void {
                $query->where('is_won', false)
                    ->where('is_lost', false)
                    ->where('stage_type', '!=', LeadStageType::Spam->value);
            })
            ->orderBy('next_follow_up_at')
            ->chunkById(100, function ($leads) use ($domainEvents, $settings, &$count): void {
                foreach ($leads as $lead) {
                    if (! $settings->followUpRemindersEnabled($lead->company_id)) {
                        continue;
                    }

                    $domainEvents->dispatch(new FollowUpDue(
                        companyId: $lead->company_id,
                        actorId: null,
                        aggregateType: CrmLead::class,
                        aggregateId: $lead->id,
                        payload: [
                            'notification_type' => 'follow_up_due',
                            'lead_id' => $lead->id,
                            'lead_title' => $lead->title,
                            'business_name' => $lead->business_name,
                            'contact_name' => $lead->contact_name,
                            'source' => $lead->source?->slug,
                            'assigned_user_id' => $lead->assigned_user_id,
                            'follow_up_at' => $lead->next_follow_up_at?->toISOString(),
                            'priority' => $lead->priority?->value ?? $lead->priority,
                        ],
                        correlationId: 'crm.lead.follow_up.due:'.$lead->id.':'.$lead->next_follow_up_at?->timestamp,
                    ));
                    $count++;
                }
            });

        $this->info("Dispatched {$count} lead follow-up reminder checks.");

        return self::SUCCESS;
    }
}
