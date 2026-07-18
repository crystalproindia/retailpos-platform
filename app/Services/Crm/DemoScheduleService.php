<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\DemoScheduleStatus;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Events\Domain\Crm\DemoScheduled;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\DemoSchedule;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DemoScheduleService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
        private readonly CrmLeadScoringService $leadScoring,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(CrmLead $lead, User $user, array $data): DemoSchedule
    {
        return DB::transaction(function () use ($lead, $user, $data): DemoSchedule {
            [$startsAt, $endsAt] = $this->times($data);
            $this->ensureNoLocalConflict($lead->company_id, $startsAt, $endsAt);
            $previousStatusId = $lead->status_id;
            $status = $this->demoScheduledStatus($lead->company_id);

            $schedule = DemoSchedule::create($this->schedulePayload($lead, $user, $data, $startsAt, $endsAt) + [
                'status' => DemoScheduleStatus::Scheduled,
            ]);

            $lead->update([
                'status_id' => $status->id,
                'next_follow_up_at' => $startsAt,
            ]);

            $activityText = 'Demo scheduled for '.$this->dateTimeLabel($schedule).'.';
            $this->recordActivity($schedule, $lead, $user, $activityText, $activityText);
            $this->auditLogger->record('crm.demo.scheduled', $schedule, 'CRM demo scheduled', [
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'from_status_id' => $previousStatusId,
                'to_status_id' => $status->id,
            ]);
            $this->domainEvents->dispatch(new DemoScheduled(
                companyId: $lead->company_id,
                actorId: $user->id,
                aggregateType: DemoSchedule::class,
                aggregateId: $schedule->id,
                payload: $this->eventPayload($schedule, $lead),
            ));
            $this->leadScoring->refresh($lead, $user);

            return $schedule->load(['assignedTo', 'scheduledBy']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reschedule(DemoSchedule $schedule, User $user, array $data): DemoSchedule
    {
        return DB::transaction(function () use ($schedule, $user, $data): DemoSchedule {
            $this->ensureActive($schedule);
            [$startsAt, $endsAt] = $this->times($data);
            $this->ensureNoLocalConflict($schedule->company_id, $startsAt, $endsAt, $schedule->id);
            $lead = $schedule->lead()->firstOrFail();

            $schedule->update(Arr::only($this->schedulePayload($lead, $user, $data, $startsAt, $endsAt), [
                'assigned_to', 'scheduled_date', 'starts_at', 'ends_at', 'timezone', 'meeting_mode', 'meeting_link', 'customer_email', 'customer_phone', 'notes',
            ]) + [
                'status' => DemoScheduleStatus::Rescheduled,
                'completed_at' => null,
                'cancelled_at' => null,
            ]);
            $lead->update(['next_follow_up_at' => $startsAt]);

            $activityText = 'Demo rescheduled for '.$this->dateTimeLabel($schedule->refresh()).'.';
            $this->recordActivity($schedule, $lead, $user, $activityText, $activityText);
            $this->auditLogger->record('crm.demo.rescheduled', $schedule, 'CRM demo rescheduled', [
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
            ]);
            $this->leadScoring->refresh($lead, $user);

            return $schedule->refresh()->load(['assignedTo', 'scheduledBy']);
        });
    }

    public function complete(DemoSchedule $schedule, User $user): DemoSchedule
    {
        $this->ensureActive($schedule);
        $lead = $schedule->lead()->firstOrFail();
        $schedule->update([
            'status' => DemoScheduleStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->recordActivity($schedule, $lead, $user, 'Demo completed.', 'Demo completed');
        $this->auditLogger->record('crm.demo.completed', $schedule, 'CRM demo completed', [
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
        ]);
        $this->leadScoring->refresh($lead, $user);

        return $schedule->refresh()->load(['assignedTo', 'scheduledBy']);
    }

    public function cancel(DemoSchedule $schedule, User $user): DemoSchedule
    {
        $this->ensureActive($schedule);
        $lead = $schedule->lead()->firstOrFail();
        $schedule->update([
            'status' => DemoScheduleStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->recordActivity($schedule, $lead, $user, 'Demo cancelled.', 'Demo cancelled');
        $this->auditLogger->record('crm.demo.cancelled', $schedule, 'CRM demo cancelled', [
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
        ]);
        $this->leadScoring->refresh($lead, $user);

        return $schedule->refresh()->load(['assignedTo', 'scheduledBy']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function times(array $data): array
    {
        $timezone = $data['timezone'] ?? config('app.timezone');
        $startsAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['demo_date'].' '.$data['start_time'], $timezone);
        $endsAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['demo_date'].' '.$data['end_time'], $timezone);

        if (! $startsAt || ! $endsAt || $endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages(['end_time' => 'The demo end time must be after the start time.']);
        }

        return [$startsAt, $endsAt];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function schedulePayload(CrmLead $lead, User $user, array $data, CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        return [
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'assigned_to' => $data['assigned_to'],
            'scheduled_by' => $user->id,
            'title' => 'Demo: '.$lead->title,
            'scheduled_date' => $startsAt->toDateString(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => $data['timezone'],
            'meeting_mode' => $data['meeting_mode'],
            'meeting_link' => $data['meeting_link'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function demoScheduledStatus(int $companyId): CrmLeadStatus
    {
        $status = CrmLeadStatus::query()
            ->where('company_id', $companyId)
            ->where('stage_type', LeadStageType::DemoScheduled->value)
            ->where('is_active', true)
            ->first();

        if ($status) {
            return $status;
        }

        return CrmLeadStatus::create([
            'company_id' => $companyId,
            'name' => 'Demo Scheduled',
            'slug' => 'demo-scheduled',
            'stage_type' => LeadStageType::DemoScheduled,
            'tone' => 'warning',
            'probability' => 60,
            'is_active' => true,
            'sort_order' => 4,
        ]);
    }

    private function recordActivity(DemoSchedule $schedule, CrmLead $lead, User $user, string $description, string $subject): void
    {
        CrmActivity::create([
            'company_id' => $lead->company_id,
            'crm_lead_id' => $lead->id,
            'assigned_user_id' => $schedule->assigned_to,
            'created_by' => $user->id,
            'type' => ActivityType::Meeting,
            'subject' => $subject,
            'description' => $description,
            'scheduled_at' => $schedule->starts_at,
            'completed_at' => in_array($schedule->status, [DemoScheduleStatus::Completed, DemoScheduleStatus::Cancelled], true) ? now() : null,
            'priority' => $lead->priority ?? LeadPriority::Medium,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(DemoSchedule $schedule, CrmLead $lead): array
    {
        return [
            'demo_schedule_id' => $schedule->id,
            'lead_id' => $lead->id,
            'lead_title' => $lead->title,
            'business_name' => $lead->business_name,
            'assigned_user_id' => $schedule->assigned_to,
            'scheduled_at' => $this->dateTimeLabel($schedule),
            'meeting_mode' => $schedule->meeting_mode?->label(),
        ];
    }

    private function dateTimeLabel(DemoSchedule $schedule): string
    {
        return $schedule->starts_at?->setTimezone($schedule->timezone)->format('d M Y, h:i A') ?? 'the selected time';
    }

    private function ensureActive(DemoSchedule $schedule): void
    {
        if (! $schedule->isActive()) {
            throw ValidationException::withMessages(['demo' => 'Only scheduled or rescheduled demos can be updated.']);
        }
    }

    private function ensureNoLocalConflict(int $companyId, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $exceptId = null): void
    {
        $conflict = DemoSchedule::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [DemoScheduleStatus::Scheduled->value, DemoScheduleStatus::Rescheduled->value])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
        if ($conflict) throw ValidationException::withMessages(['start_time' => 'Selected time conflicts with an existing booking.']);
    }
}
