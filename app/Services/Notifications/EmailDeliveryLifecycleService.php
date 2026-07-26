<?php

namespace App\Services\Notifications;

use App\Enums\Notifications\EmailDeliveryStatus;
use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmailDeliveryLifecycleService
{
    /** @param array<string, mixed> $metadata */
    public function recordQueued(NotificationDelivery $delivery, string $eventType = 'delivery.queued', array $metadata = []): NotificationDelivery
    {
        NotificationDeliveryEvent::query()->create([
            'company_id' => $delivery->company_id,
            'notification_delivery_id' => $delivery->id,
            'provider' => $delivery->provider,
            'event_type' => $eventType,
            'to_status' => EmailDeliveryStatus::Queued->value,
            'metadata' => $this->safeMetadata($metadata),
            'occurred_at' => now(),
            'processed_at' => now(),
        ]);

        return $delivery;
    }

    /** @param array<string, mixed> $metadata */
    public function transition(NotificationDelivery $delivery, EmailDeliveryStatus $to, string $eventType, array $metadata = [], ?string $providerEventId = null, ?\DateTimeInterface $occurredAt = null): NotificationDelivery
    {
        return DB::transaction(function () use ($delivery, $to, $eventType, $metadata, $providerEventId, $occurredAt): NotificationDelivery {
            $record = NotificationDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $from = $this->status($record);

            if ($from === $to) {
                return $record;
            }

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages(['status' => "Email delivery cannot move from {$from->value} to {$to->value}."]);
            }

            $timestamp = now();
            $attributes = ['status' => $to->value];
            if ($to === EmailDeliveryStatus::Processing) {
                $attributes['attempt_count'] = $record->attempt_count + 1;
                $attributes['failure_reason'] = null;
                $attributes['next_retry_at'] = null;
            }
            if ($to === EmailDeliveryStatus::Sent) {
                $attributes['sent_at'] = $timestamp;
                $attributes['failure_reason'] = null;
                $attributes['next_retry_at'] = null;
            }
            if ($to === EmailDeliveryStatus::Delivered) {
                $attributes['delivered_at'] = $timestamp;
                $attributes['failure_reason'] = null;
                $attributes['next_retry_at'] = null;
            }
            if (in_array($to, [EmailDeliveryStatus::TemporarilyFailed, EmailDeliveryStatus::PermanentlyFailed, EmailDeliveryStatus::Bounced, EmailDeliveryStatus::Rejected], true)) {
                $attributes['failed_at'] = $timestamp;
            }

            $record->update($attributes);
            NotificationDeliveryEvent::query()->create([
                'company_id' => $record->company_id,
                'notification_delivery_id' => $record->id,
                'provider' => $record->provider,
                'provider_event_id' => $providerEventId,
                'event_type' => $eventType,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'metadata' => $this->safeMetadata($metadata),
                'occurred_at' => $occurredAt ?? $timestamp,
                'processed_at' => $timestamp,
            ]);

            return $record->refresh();
        });
    }

    public function markTemporaryFailure(NotificationDelivery $delivery, string $reason): NotificationDelivery
    {
        $record = $this->transition($delivery, EmailDeliveryStatus::TemporarilyFailed, 'delivery.temporarily_failed');
        $delay = $this->retryDelay($record->attempt_count);
        $record->update(['failure_reason' => $reason, 'next_retry_at' => now()->addSeconds($delay)]);

        return $record->refresh();
    }

    public function markPermanentFailure(NotificationDelivery $delivery, string $reason): NotificationDelivery
    {
        $record = $this->transition($delivery, EmailDeliveryStatus::PermanentlyFailed, 'delivery.permanently_failed');
        $record->update(['failure_reason' => $reason, 'next_retry_at' => null]);

        return $record->refresh();
    }

    public function retryDelay(int $attempt): int
    {
        return min(900, 60 * (2 ** max(0, $attempt - 1)));
    }

    public function status(NotificationDelivery $delivery): EmailDeliveryStatus
    {
        return match ($delivery->status) {
            'sending' => EmailDeliveryStatus::Processing,
            'failed' => EmailDeliveryStatus::PermanentlyFailed,
            default => EmailDeliveryStatus::tryFrom((string) $delivery->status) ?? EmailDeliveryStatus::Queued,
        };
    }

    /** @param array<string, mixed> $metadata */
    private function safeMetadata(array $metadata): array
    {
        return collect($metadata)->only(['provider_event_type', 'provider_message_id', 'retry_after_seconds', 'manual_resend_from'])->map(function ($value): string|int|bool|null {
            if (is_string($value)) return str($value)->replace(["\r", "\n"], ' ')->squish()->limit(160)->toString();
            return is_scalar($value) ? $value : null;
        })->filter(fn ($value) => $value !== null)->all();
    }
}
