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

class NotificationService
{
    public function __construct(
        private readonly EventCatalog $eventCatalog,
        private readonly RecipientResolver $recipientResolver,
        private readonly NotificationTemplateRenderer $templateRenderer,
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

                $this->channel($channel)->send($recipient, $event, $message, $delivery);
            });
        });
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
}
