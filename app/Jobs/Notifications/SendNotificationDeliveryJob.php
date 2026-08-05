<?php

namespace App\Jobs\Notifications;

use App\Models\NotificationDelivery;
use App\Enums\Notifications\EmailDeliveryStatus;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Notifications\EmailDeliveryLifecycleService;
use App\Services\Crm\InvoiceReminderService;
use App\Services\Saas\PublicSignupOtpDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendNotificationDeliveryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public readonly int $deliveryId) {}

    public function handle(EmailDeliveryService $emailDelivery, EmailDeliveryLifecycleService $lifecycle, ?InvoiceReminderService $reminders = null, ?PublicSignupOtpDeliveryService $signupOtps = null): void
    {
        $delivery = NotificationDelivery::query()->findOrFail($this->deliveryId);

        if (! in_array($delivery->status, ['queued', 'temporarily_failed'], true)) return;

        $reminders ??= app(InvoiceReminderService::class);
        $signupOtps ??= app(PublicSignupOtpDeliveryService::class);
        if (! $reminders->canSendQueuedAutomatic($delivery)) {
            $cancelled = $lifecycle->transition($delivery, EmailDeliveryStatus::Cancelled, 'delivery.reminder_cancelled');
            $cancelled->update(['failure_reason' => 'Automatic reminder cancelled because the invoice is no longer eligible.', 'next_retry_at' => null]);

            return;
        }

        if (! $signupOtps->canSendQueued($delivery)) {
            $cancelled = $lifecycle->transition($delivery, EmailDeliveryStatus::Cancelled, 'delivery.signup_otp_cancelled');
            $cancelled->update(['failure_reason' => 'Signup verification email is no longer active.', 'next_retry_at' => null, 'sensitive_payload' => null]);

            return;
        }

        if ($delivery->channel !== 'email' || ! $delivery->recipient || ! filter_var($delivery->recipient, FILTER_VALIDATE_EMAIL)) {
            $rejected = $lifecycle->transition($delivery, EmailDeliveryStatus::Rejected, 'delivery.rejected');
            $rejected->update(['failure_reason' => 'Email delivery requires a valid recipient address.', 'next_retry_at' => null]);

            return;
        }

        $emailDelivery->send($delivery);
    }

    public function failed(Throwable $exception): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);
        if (! $delivery || ! in_array($delivery->status, ['processing', 'temporarily_failed'], true)) return;
        app(EmailDeliveryLifecycleService::class)->markPermanentFailure($delivery, $delivery->failure_reason ?: 'Email transport could not complete delivery.');
    }
}
