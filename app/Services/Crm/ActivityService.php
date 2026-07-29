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
            'timezone' => $data['timezone'] ?? config('app.timezone', 'UTC'),
            'follow_up_status' => 'pending',
        ]);

        $this->auditLogger->record('crm.activity.created', $activity, 'CRM activity created');

        return $activity;
    }

    public function complete(CrmActivity $activity, User $user, ?string $outcome = null): CrmActivity
    {
        $activity->update([
            'completed_at' => now(),
            'completed_by' => $user->id,
            'outcome' => $outcome,
            'follow_up_status' => 'completed',
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
            'completed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'follow_up_status' => 'pending',
        ]);

        $this->auditLogger->record('crm.activity.rescheduled', $activity, 'CRM activity rescheduled');

        return $activity;
    }

    public function cancel(CrmActivity $activity, User $user, ?string $outcome = null): CrmActivity
    {
        $activity->update([
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
            'outcome' => $outcome,
            'follow_up_status' => 'cancelled',
        ]);
        $this->auditLogger->record('crm.follow_up.cancelled', $activity, 'CRM follow-up cancelled', ['company_id' => $activity->company_id]);

        return $activity;
    }
}
