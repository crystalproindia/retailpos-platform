<?php

namespace App\Services\Notifications;

use App\Enums\Notifications\EmailDeliveryStatus;
use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryEvent;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EmailDeliveryProviderEventService
{
    public function __construct(private readonly EmailDeliveryLifecycleService $lifecycle) {}

    /** @param array<string, mixed> $payload */
    public function process(string $provider, array $payload): bool
    {
        $provider = str($provider)->lower()->trim()->limit(80)->toString();
        $eventId = str((string) ($payload['event_id'] ?? ''))->trim()->limit(191)->toString();
        $messageId = str((string) ($payload['provider_message_id'] ?? ''))->trim()->limit(191)->toString();
        $eventType = str((string) ($payload['event_type'] ?? ''))->lower()->trim()->limit(80)->toString();

        if (! $provider || ! $eventId || ! $messageId || ! $this->statusFor($eventType)) {
            throw ValidationException::withMessages(['event' => 'The delivery event is not valid.']);
        }

        $occurredAt = Carbon::parse((string) ($payload['timestamp'] ?? ''));
        if (abs($occurredAt->diffInSeconds(now())) > config('email-delivery.webhook.max_age_seconds', 300)) {
            throw ValidationException::withMessages(['event' => 'The delivery event has expired.']);
        }

        if (NotificationDeliveryEvent::query()->where('provider', $provider)->where('provider_event_id', $eventId)->exists()) {
            return false;
        }

        $delivery = NotificationDelivery::query()
            ->where('channel', 'email')
            ->where('provider', $provider)
            ->where('provider_message_id', $messageId)
            ->first();

        if (! $delivery || (int) ($payload['company_id'] ?? 0) !== $delivery->company_id) {
            throw ValidationException::withMessages(['event' => 'The delivery event could not be matched.']);
        }

        $this->lifecycle->transition($delivery, $this->statusFor($eventType), 'provider.'.$eventType, [
            'provider_event_type' => $eventType,
        ], $eventId, $occurredAt);

        return true;
    }

    private function statusFor(string $eventType): ?EmailDeliveryStatus
    {
        return match ($eventType) {
            'delivered' => EmailDeliveryStatus::Delivered,
            'bounced', 'bounce' => EmailDeliveryStatus::Bounced,
            'rejected' => EmailDeliveryStatus::Rejected,
            'permanently_failed', 'hard_failed' => EmailDeliveryStatus::PermanentlyFailed,
            'temporarily_failed', 'soft_failed', 'deferred' => EmailDeliveryStatus::TemporarilyFailed,
            default => null,
        };
    }
}
