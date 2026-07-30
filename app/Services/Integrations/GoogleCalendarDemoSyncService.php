<?php

namespace App\Services\Integrations;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Events\Domain\Crm\DemoGoogleCalendarSyncFailed;
use App\Events\Domain\Crm\DemoGoogleCalendarSynced;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\DemoSchedule;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;

class GoogleCalendarDemoSyncService
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendar,
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    public function sync(DemoSchedule $demo, User $actor, bool $createMeet = false): GoogleCalendarSyncResult
    {
        $demo->loadMissing(['lead', 'assignedTo']);
        $isUpdate = filled($demo->external_calendar_event_id);
        $demo->update(['calendar_sync_status' => 'pending']);

        try {
            $event = $this->googleCalendar->syncDemo($demo, $createMeet);
            $meetingLink = $event['meeting_link'];
            $hadExternalMeetingLink = filled($demo->external_meeting_link);
            $demo->update([
                'external_calendar_provider' => 'google',
                'external_calendar_event_id' => $event['event_id'],
                'external_calendar_event_url' => $event['event_url'],
                'external_meeting_link' => $meetingLink ?: $demo->external_meeting_link,
                'meeting_link' => $meetingLink ?: $demo->meeting_link,
                'calendar_sync_status' => 'synced',
                'calendar_synced_at' => now(),
            ]);
            $this->googleCalendar->markSynced($demo->company_id);

            $subject = $isUpdate ? 'Google Calendar event updated' : 'Demo synced to Google Calendar';
            $description = $meetingLink && ! $hadExternalMeetingLink
                ? $subject.'. Google Meet link created.'
                : $subject.'.';
            $this->recordActivity($demo, $actor, $subject, $description);
            if ($meetingLink && ! $hadExternalMeetingLink) {
                $this->recordActivity($demo, $actor, 'Google Meet link created', 'Google Meet link created for this demo.');
                $this->auditLogger->record('crm.demo.google_meet_created', $demo, 'Google Meet link created', [
                    'company_id' => $demo->company_id,
                    'lead_id' => $demo->lead_id,
                ]);
            }
            $this->auditLogger->record('crm.demo.google_calendar_synced', $demo, $subject, [
                'company_id' => $demo->company_id,
                'lead_id' => $demo->lead_id,
                'calendar_event_id' => $event['event_id'],
                'operation' => $isUpdate ? 'update' : 'create',
            ]);
            $this->domainEvents->dispatch(new DemoGoogleCalendarSynced(
                companyId: $demo->company_id,
                actorId: $actor->id,
                aggregateType: DemoSchedule::class,
                aggregateId: $demo->id,
                payload: $this->eventPayload($demo, $isUpdate ? 'updated' : 'synced'),
            ));

            return new GoogleCalendarSyncResult(true, $isUpdate ? 'Google Calendar event updated.' : 'Demo synced to Google Calendar.');
        } catch (GoogleCalendarException $exception) {
            return $this->markFailed($demo, $actor, $exception->getMessage());
        }
    }

    public function updateIfSynced(DemoSchedule $demo, User $actor, bool $createMeet = false): ?GoogleCalendarSyncResult
    {
        if ($demo->external_calendar_provider !== 'google' || blank($demo->external_calendar_event_id)) {
            return null;
        }

        return $this->sync($demo, $actor, $createMeet);
    }

    public function cancelIfSynced(DemoSchedule $demo, User $actor): ?GoogleCalendarSyncResult
    {
        if ($demo->external_calendar_provider !== 'google' || blank($demo->external_calendar_event_id)) {
            return null;
        }

        try {
            $this->googleCalendar->cancelDemo($demo);
            $demo->update([
                'calendar_sync_status' => 'cancelled',
                'calendar_synced_at' => now(),
            ]);
            $this->recordActivity($demo, $actor, 'Google Calendar event cancelled', 'Google Calendar event cancelled.');
            $this->auditLogger->record('crm.demo.google_calendar_cancelled', $demo, 'Google Calendar event cancelled', [
                'company_id' => $demo->company_id,
                'lead_id' => $demo->lead_id,
                'calendar_event_id' => $demo->external_calendar_event_id,
            ]);

            return new GoogleCalendarSyncResult(true, 'Google Calendar event cancelled.');
        } catch (GoogleCalendarException $exception) {
            return $this->markFailed($demo, $actor, $exception->getMessage());
        }
    }

    private function markFailed(DemoSchedule $demo, User $actor, string $message): GoogleCalendarSyncResult
    {
        $demo->update(['calendar_sync_status' => 'failed']);
        $this->recordActivity($demo, $actor, 'Google Calendar sync failed', 'Google Calendar sync failed. '.$message);
        $this->auditLogger->record('crm.demo.google_calendar_sync_failed', $demo, 'Google Calendar sync failed', [
            'company_id' => $demo->company_id,
            'lead_id' => $demo->lead_id,
        ]);
        $this->domainEvents->dispatch(new DemoGoogleCalendarSyncFailed(
            companyId: $demo->company_id,
            actorId: $actor->id,
            aggregateType: DemoSchedule::class,
            aggregateId: $demo->id,
            payload: $this->eventPayload($demo, 'failed'),
        ));

        return new GoogleCalendarSyncResult(false, $message);
    }

    private function recordActivity(DemoSchedule $demo, User $actor, string $subject, string $description): void
    {
        $lead = $demo->lead;

        CrmActivity::create([
            'company_id' => $demo->company_id,
            'crm_lead_id' => $demo->lead_id,
            'assigned_user_id' => $demo->assigned_to,
            'created_by' => $actor->id,
            'type' => ActivityType::Note,
            'subject' => $subject,
            'description' => $description,
            'scheduled_at' => $demo->starts_at,
            'completed_at' => now(),
            'priority' => $lead?->priority ?? LeadPriority::Medium,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(DemoSchedule $demo, string $operation): array
    {
        return [
            'demo_schedule_id' => $demo->id,
            'lead_id' => $demo->lead_id,
            'lead_title' => $demo->lead?->title,
            'business_name' => $demo->lead?->business_name,
            'assigned_user_id' => $demo->assigned_to,
            'operation' => $operation,
        ];
    }
}
