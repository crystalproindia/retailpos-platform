<?php

namespace App\Services\Crm;

use App\Contracts\Crm\SalesMessageGeneratorInterface;
use App\Data\Crm\CrmLeadScoreResult;
use App\Data\Crm\GeneratedSalesMessage;
use App\Enums\Crm\ActivityType;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\User;
use App\Services\AuditLogger;

class CrmFollowUpAssistantService
{
    public function __construct(
        private readonly SalesMessageGeneratorInterface $messages,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $options */
    public function preview(CrmLead $lead, CrmLeadScoreResult $score, array $options = []): GeneratedSalesMessage
    {
        return $this->messages->generate($lead, $score, $options);
    }

    /** @param array<string, mixed> $options */
    public function generate(CrmLead $lead, User $actor, CrmLeadScoreResult $score, array $options): GeneratedSalesMessage
    {
        $message = $this->preview($lead, $score, $options);
        CrmActivity::create([
            'company_id' => $lead->company_id,
            'crm_lead_id' => $lead->id,
            'assigned_user_id' => $lead->assigned_user_id,
            'created_by' => $actor->id,
            'type' => ActivityType::FollowUp,
            'subject' => 'AI follow-up draft generated',
            'description' => 'A rule-based follow-up draft was generated for review. No message was sent.',
            'scheduled_at' => now(),
            'completed_at' => now(),
            'priority' => $score->priority,
        ]);
        $this->auditLogger->record('crm.follow_up.generated', $lead, 'AI follow-up draft generated', [
            'message_type' => $options['message_type'] ?? null,
            'tone' => $options['tone'] ?? null,
            'length' => $options['length'] ?? null,
            'sent' => false,
        ]);

        return $message;
    }
}
