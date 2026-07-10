<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmActivity;
use App\Models\User;
use App\Services\AuditLogger;

class ActivityService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): CrmActivity
    {
        $activity = CrmActivity::create($data + [
            'company_id' => $user->company_id,
            'assigned_user_id' => $data['assigned_user_id'] ?? $user->id,
            'created_by' => $user->id,
        ]);

        $this->auditLogger->record('crm.activity.created', $activity, 'CRM activity created');

        return $activity;
    }

    public function complete(CrmActivity $activity, User $user, ?string $outcome = null): CrmActivity
    {
        $activity->update([
            'completed_at' => now(),
            'outcome' => $outcome,
        ]);

        $this->auditLogger->record('crm.activity.completed', $activity, 'CRM activity completed', [
            'completed_by' => $user->id,
        ]);

        return $activity;
    }

    public function reschedule(CrmActivity $activity, string $scheduledAt): CrmActivity
    {
        $activity->update([
            'scheduled_at' => $scheduledAt,
            'completed_at' => null,
        ]);

        $this->auditLogger->record('crm.activity.rescheduled', $activity, 'CRM activity rescheduled');

        return $activity;
    }
}
