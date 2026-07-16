<?php

namespace App\Services\Notifications;

use App\Contracts\Events\DomainEvent;
use App\Contracts\Notifications\NotificationChannel;
use App\Models\DomainEventLog;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notifications\Channels\DatabaseNotificationChannel;
use App\Services\Notifications\Channels\EmailNotificationChannel;
use App\Services\Notifications\Channels\UnsupportedNotificationChannel;
use App\Support\Events\EventCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function __construct(
        private readonly EventCatalog $eventCatalog,
        private readonly RecipientResolver $recipientResolver,
        private readonly NotificationTemplateRenderer $templateRenderer,
        private readonly LeadNotificationSettings $leadSettings,
    ) {}

    public function dispatchForEvent(DomainEvent $event, DomainEventLog $eventLog): void
    {
        $recipients = $this->recipientResolver->resolve($event);

        $recipients->each(function (User $recipient) use ($event, $eventLog): void {
            collect($this->channelsFor($recipient, $event))->each(function (string $channel) use ($recipient, $event, $eventLog): void {
                $message = $this->templateRenderer->render($event, $channel);
                $delivery = NotificationDelivery::create([
                    'company_id' => $recipient->company_id,
                    'user_id' => $recipient->id,
                    'domain_event_log_id' => $eventLog->id,
                    'event_key' => $event->eventKey(),
                    'channel' => $channel,
                    'recipient' => $channel === 'email' ? $recipient->email : (string) $recipient->id,
                    'status' => 'pending',
                    'payload' => $message,
                ]);

                try {
                    $this->channel($channel)->send($recipient, $event, $message, $delivery);
                } catch (Throwable $exception) {
                    $delivery->update([
                        'status' => 'failed',
                        'failure_reason' => str($exception->getMessage())->limit(500)->toString(),
                        'failed_at' => now(),
                    ]);

                    Log::warning('Notification delivery failed without interrupting the domain action.', [
                        'event_key' => $event->eventKey(),
                        'delivery_id' => $delivery->id,
                    ]);
                }
            });
        });

        $this->dispatchConfiguredLeadEmail($event, $eventLog);
    }

    /**
     * @return array<int, string>
     */
    public function channelsFor(User $recipient, DomainEvent $event): array
    {
        $preference = NotificationPreference::query()
            ->where('company_id', $recipient->company_id)
            ->where('user_id', $recipient->id)
            ->where('event_key', $event->eventKey())
            ->first();

        $channels = collect($this->eventCatalog->defaultChannels($event->eventKey()))
            ->filter(fn (string $channel): bool => in_array($channel, $this->eventCatalog->allowedChannels($event->eventKey()), true));

        if ($preference) {
            $channels = collect(['database', 'email', 'whatsapp', 'sms', 'push'])
                ->filter(fn (string $channel): bool => (bool) $preference->{$channel.'_enabled'});
        }

        if ($preference && $preference->quiet_hours_enabled && $this->inQuietHours($preference)) {
            $channels = $channels->reject(fn (string $channel): bool => $channel === 'email');
        }

        if ($this->isLeadAlertEvent($event) && ! $this->leadSettings->emailEnabled($recipient->company_id)) {
            $channels = $channels->reject(fn (string $channel): bool => $channel === 'email');
        }

        return $channels->unique()->values()->all();
    }

    /**
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function groupedPreferences(User $user): Collection
    {
        $preferences = NotificationPreference::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('event_key');

        return $this->eventCatalog->grouped()
            ->map(fn (Collection $events): Collection => $events->map(function (array $definition, string $eventKey) use ($preferences): array {
                return [
                    'event_key' => $eventKey,
                    'definition' => $definition,
                    'preference' => $preferences->get($eventKey),
                ];
            })->values());
    }

    private function channel(string $channel): NotificationChannel
    {
        if (! $this->eventCatalog->channelEnabled($channel) && in_array($channel, ['whatsapp', 'sms', 'push'], true)) {
            return new UnsupportedNotificationChannel($channel);
        }

        return match ($channel) {
            'database' => app(DatabaseNotificationChannel::class),
            'email' => app(EmailNotificationChannel::class),
            default => new UnsupportedNotificationChannel($channel),
        };
    }

    private function inQuietHours(NotificationPreference $preference): bool
    {
        if (! $preference->quiet_hours_start || ! $preference->quiet_hours_end) {
            return false;
        }

        $now = CarbonImmutable::now($preference->timezone ?: config('app.timezone'))->format('H:i:s');

        return $preference->quiet_hours_start <= $preference->quiet_hours_end
            ? $now >= $preference->quiet_hours_start && $now <= $preference->quiet_hours_end
            : $now >= $preference->quiet_hours_start || $now <= $preference->quiet_hours_end;
    }

    private function dispatchConfiguredLeadEmail(DomainEvent $event, DomainEventLog $eventLog): void
    {
        if (! $this->isLeadAlertEvent($event) || ! $event->companyId() || ! $this->leadSettings->emailEnabled($event->companyId())) {
            return;
        }

        if (in_array($event->eventKey(), ['crm.follow_up.due', 'crm.follow_up.overdue'], true)
            && ! $this->leadSettings->followUpRemindersEnabled($event->companyId())) {
            return;
        }

        $email = $this->leadSettings->emailAddress($event->companyId());

        if (! $email) {
            return;
        }

        $message = $this->templateRenderer->render($event, 'email');
        $recipient = new User([
            'company_id' => $event->companyId(),
            'email' => $email,
        ]);
        $delivery = NotificationDelivery::create([
            'company_id' => $event->companyId(),
            'domain_event_log_id' => $eventLog->id,
            'event_key' => $event->eventKey(),
            'channel' => 'email',
            'recipient' => $email,
            'status' => 'pending',
            'payload' => $message,
        ]);

        try {
            $this->channel('email')->send($recipient, $event, $message, $delivery);
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'failure_reason' => str($exception->getMessage())->limit(500)->toString(),
                'failed_at' => now(),
            ]);

            Log::warning('Configured lead-alert email could not be queued.', [
                'event_key' => $event->eventKey(),
                'delivery_id' => $delivery->id,
            ]);
        }
    }

    private function isLeadAlertEvent(DomainEvent $event): bool
    {
        return in_array($event->eventKey(), [
            'crm.lead.created',
            'crm.lead.assigned',
            'crm.lead.status_changed',
            'crm.follow_up.due',
            'crm.follow_up.overdue',
        ], true);
    }
}
