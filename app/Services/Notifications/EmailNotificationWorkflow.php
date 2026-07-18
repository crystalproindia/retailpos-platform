<?php

namespace App\Services\Notifications;

use App\Contracts\Events\DomainEvent;
use App\Enums\UserRole;
use App\Models\Crm\CrmLead;
use App\Models\Crm\DemoSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EmailNotificationWorkflow
{
    public function __construct(
        private readonly EmailDeliveryService $deliveries,
        private readonly LeadNotificationSettings $leadSettings,
    ) {}

    public function queueFor(DomainEvent $event): void
    {
        if (! $event->companyId()) {
            return;
        }

        match ($event->eventKey()) {
            'crm.lead.created' => $this->leadReceived($event),
            'crm.demo.scheduled' => $this->demo($event, 'demo_confirmation', 'Demo scheduled', 'Your RetailPOS demo is confirmed.'),
            'crm.demo.rescheduled' => $this->demo($event, 'demo_rescheduled', 'Demo rescheduled', 'Your RetailPOS demo has been rescheduled.'),
            'crm.demo.cancelled' => $this->demo($event, 'demo_cancelled', 'Demo cancelled', 'Your RetailPOS demo has been cancelled.'),
            default => null,
        };
    }

    private function leadReceived(DomainEvent $event): void
    {
        if (! $this->leadSettings->emailEnabled($event->companyId())
            && ! $this->deliveries->configuration($event->companyId())['configured']) {
            return;
        }

        $lead = $this->related(CrmLead::class, $event);
        if (! $lead) {
            return;
        }

        $recipients = $this->internalRecipients($event->companyId(), $lead->assigned_user_id);
        foreach ($recipients as $recipient) {
            $this->deliveries->queue(
                companyId: $event->companyId(),
                recipient: $recipient->email,
                recipientName: $recipient->name,
                subject: 'New RetailPOS lead: '.$lead->title,
                templateKey: ($event->payload()['notification_type'] ?? 'lead_received') === 'demo_request_received' ? 'demo_request_received_internal' : 'lead_received_internal',
                payload: [
                    'heading' => 'New lead received',
                    'greeting' => 'Hello '.$recipient->name.',',
                    'message' => 'A new lead needs review in the Command Center.',
                    'details' => array_filter([
                        'Contact' => $lead->contact_name,
                        'Company' => $lead->business_name,
                        'Email' => $lead->email,
                        'Phone' => $lead->phone,
                        'Country' => $lead->country,
                        'Interested service' => $lead->business_type,
                        'Source' => $lead->source?->name,
                        'Requirement' => $lead->description,
                    ]),
                    'action_url' => route('crm.leads.show', $lead),
                    'action_label' => 'Open lead',
                ],
                related: $lead,
                idempotencyKey: 'lead-received:'.$lead->id.':'.$recipient->email,
                createdBy: $this->actor($event),
            );
        }
    }

    private function demo(DomainEvent $event, string $templateKey, string $subject, string $customerMessage): void
    {
        $demo = $this->related(DemoSchedule::class, $event);
        if (! $demo) {
            return;
        }

        $lead = $demo->lead()->with('source')->first();
        $details = array_filter([
            'Date and time' => $demo->starts_at?->setTimezone($demo->timezone)->format('d M Y, h:i A'),
            'Timezone' => $demo->timezone,
            'Meeting mode' => $demo->meeting_mode?->label(),
            'Consultant' => $demo->assignedTo?->name,
            'Meeting link' => $demo->meeting_link,
        ]);

        if ($this->deliveries->configuration($event->companyId())['configured'] && filled($demo->customer_email) && filter_var($demo->customer_email, FILTER_VALIDATE_EMAIL)) {
            $this->deliveries->queue(
                companyId: $event->companyId(), recipient: $demo->customer_email, recipientName: $lead?->contact_name,
                subject: $subject.' | RetailPOS', templateKey: $templateKey, related: $demo, createdBy: $this->actor($event),
                idempotencyKey: $templateKey.':'.$demo->id.':'.$demo->customer_email.':'.$demo->updated_at?->timestamp,
                payload: ['heading' => $subject, 'greeting' => 'Hello '.($lead?->contact_name ?: 'there').',', 'message' => $customerMessage.' Contact us if you need to reschedule or cancel.', 'details' => $details],
            );
        }

        foreach ($this->internalRecipients($event->companyId(), $demo->assigned_to) as $recipient) {
            $this->deliveries->queue(
                companyId: $event->companyId(), recipient: $recipient->email, recipientName: $recipient->name,
                subject: $subject.': '.($lead?->business_name ?: $lead?->title ?: 'Demo'), templateKey: $templateKey.'_internal', related: $demo, createdBy: $this->actor($event),
                idempotencyKey: $templateKey.'-internal:'.$demo->id.':'.$recipient->email.':'.$demo->updated_at?->timestamp,
                payload: ['heading' => $subject, 'greeting' => 'Hello '.$recipient->name.',', 'message' => 'A demo schedule has been updated.', 'details' => $details + ['Customer' => $lead?->contact_name, 'Company' => $lead?->business_name, 'Phone' => $demo->customer_phone], 'action_url' => route('crm.leads.show', $demo->lead_id), 'action_label' => 'Open lead'],
            );
        }
    }

    /** @return Collection<int, User> */
    private function internalRecipients(int $companyId, ?int $assignedId)
    {
        return User::query()->where('company_id', $companyId)->where('is_active', true)
            ->where(function ($query) use ($assignedId): void {
                $query->whereIn('role', [UserRole::Administrator->value, UserRole::Manager->value])->when($assignedId, fn ($query) => $query->orWhere('id', $assignedId));
            })
            ->whereNotNull('email')->get()->filter(fn (User $user): bool => filter_var($user->email, FILTER_VALIDATE_EMAIL))->unique('email');
    }

    /** @template T of Model
     * @param  class-string<T>  $class
     * @return T|null
     */
    private function related(string $class, DomainEvent $event): ?Model
    {
        return $event->aggregateType() === $class ? $class::query()->find($event->aggregateId()) : null;
    }

    private function actor(DomainEvent $event): ?User
    {
        return $event->actorId() ? User::query()->find($event->actorId()) : null;
    }
}
