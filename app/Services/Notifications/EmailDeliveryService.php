<?php

namespace App\Services\Notifications;

use App\Jobs\Notifications\SendNotificationDeliveryJob;
use App\Mail\CommandCenterEmail;
use App\Models\CompanyEmailSetting;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Repositories\Integrations\CompanyEmailSettingsRepository;
use App\Services\AuditLogger;
use App\Services\Crm\InvoiceEmailAttachmentService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailDeliveryService
{
    public function __construct(
        private readonly CompanyEmailSettingsRepository $settings,
        private readonly AuditLogger $auditLogger,
        private readonly InvoiceEmailAttachmentService $invoiceAttachments,
        private readonly EmailDeliveryLifecycleService $lifecycle,
    ) {}

    /** @return array{configured: bool, source: string, setting: ?CompanyEmailSetting, reason: ?string} */
    public function configuration(int $companyId): array
    {
        $setting = $this->settings->forCompany($companyId);

        if ($setting && ! $setting->is_enabled) {
            return ['configured' => false, 'source' => 'company', 'setting' => $setting, 'reason' => 'Email delivery is disabled for this company.'];
        }

        if ($setting?->isComplete()) {
            return ['configured' => true, 'source' => 'company', 'setting' => $setting, 'reason' => null];
        }

        $environmentConfigured = config('mail.default') === 'smtp'
            && filled(config('mail.mailers.smtp.host'))
            && filled(config('mail.from.address'));

        return [
            'configured' => $environmentConfigured,
            'source' => $environmentConfigured ? 'environment' : 'none',
            'setting' => $setting,
            'reason' => $environmentConfigured ? null : 'SMTP is not configured.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queue(
        int $companyId,
        string $recipient,
        string $subject,
        string $templateKey,
        array $payload,
        ?object $related = null,
        ?User $createdBy = null,
        ?string $idempotencyKey = null,
        ?string $recipientName = null,
        ?string $reminderStage = null,
        ?string $reminderSource = null,
        ?array $sensitivePayload = null,
    ): NotificationDelivery {
        $recipient = $this->cleanAddress($recipient);
        $reminderStage = $this->cleanReminderValue($reminderStage, 32);
        $reminderSource = $this->cleanReminderValue($reminderSource, 16);
        $idempotencyKey ??= hash('sha256', implode('|', [$templateKey, $related?->getMorphClass() ?? '', $related?->getKey() ?? '', $recipient]));
        $configuration = $this->configuration($companyId);

        try {
            $delivery = NotificationDelivery::query()->firstOrCreate(
                ['company_id' => $companyId, 'idempotency_key' => $idempotencyKey],
                [
                    'created_by' => $createdBy?->id,
                    'related_type' => $related?->getMorphClass(),
                    'related_id' => $related?->getKey(),
                    'event_key' => 'email.'.$templateKey,
                    'template_key' => $templateKey,
                    'reminder_stage' => $reminderStage,
                    'reminder_source' => $reminderSource,
                    'channel' => 'email',
                    'recipient' => $recipient,
                    'recipient_name' => $recipientName,
                    'subject' => $this->cleanSubject($subject),
                    'status' => $configuration['configured'] ? 'queued' : 'skipped_not_configured',
                    'queued_at' => $configuration['configured'] ? now() : null,
                    'payload' => $this->safePayload($payload),
                    'sensitive_payload' => $configuration['configured'] && $sensitivePayload ? $this->safeSensitivePayload($sensitivePayload) : null,
                    'failure_reason' => $configuration['configured'] ? null : $configuration['reason'],
                ],
            );
        } catch (QueryException) {
            $delivery = NotificationDelivery::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
        }

        if ($delivery->wasRecentlyCreated) {
            if ($configuration['configured']) $this->lifecycle->recordQueued($delivery);
            $this->auditLogger->record('email.queued', $delivery, 'Email delivery queued', ['company_id' => $companyId, 'template_key' => $templateKey]);
            if ($configuration['configured']) {
                SendNotificationDeliveryJob::dispatch($delivery->id);
            }
        }

        return $delivery;
    }

    public function send(NotificationDelivery $delivery): void
    {
        $configuration = $this->configuration($delivery->company_id);
        if (! $configuration['configured']) {
            $delivery->update(['status' => 'skipped_not_configured', 'failure_reason' => $configuration['reason']]);

            return;
        }

        $delivery = $this->lifecycle->transition($delivery, \App\Enums\Notifications\EmailDeliveryStatus::Processing, 'delivery.processing');

        // Sensitive values, such as short-lived OTPs, remain encrypted at rest until the queued worker sends them.
        $payload = array_replace($delivery->payload ?? [], $delivery->sensitive_payload ?? []);
        try {
            $invoiceAttachment = $this->invoiceAttachments->forDelivery($delivery);
            $attachments = $invoiceAttachment ? [$invoiceAttachment] : [];
        } catch (Throwable $exception) {
            $delivery = $this->lifecycle->markTemporaryFailure($delivery, 'Invoice PDF attachment could not be generated.');
            $this->auditLogger->record('email.invoice_attachment_failed', $delivery, 'Invoice PDF attachment generation failed', [
                'company_id' => $delivery->company_id,
                'template_key' => $delivery->template_key,
            ]);

            throw $exception;
        }
        try {
            $sender = $this->sender($configuration);
            $this->mailer($configuration)->to($delivery->recipient, $delivery->recipient_name)->send(new CommandCenterEmail(
                emailSubject: $delivery->subject ?: ($payload['heading'] ?? 'RetailPOS notification'),
                heading: (string) ($payload['heading'] ?? 'RetailPOS notification'),
                greeting: (string) ($payload['greeting'] ?? 'Hello,'),
                messageText: (string) ($payload['message'] ?? ''),
                details: (array) ($payload['details'] ?? []),
                actionUrl: $payload['action_url'] ?? null,
                actionLabel: $payload['action_label'] ?? null,
                fromAddress: $sender['address'],
                fromName: $sender['name'],
                replyToAddress: $sender['reply_to'],
                attachmentData: $attachments,
            ));
        } catch (Throwable $exception) {
            $this->lifecycle->markTemporaryFailure($delivery, 'Email transport could not complete delivery.');

            throw $exception;
        }

        $delivery = $this->lifecycle->transition($delivery, \App\Enums\Notifications\EmailDeliveryStatus::Sent, 'delivery.sent');
        if ($delivery->sensitive_payload !== null) {
            $delivery->update(['sensitive_payload' => null]);
        }
        $this->auditLogger->record('email.sent', $delivery, 'Email delivery sent', ['company_id' => $delivery->company_id, 'template_key' => $delivery->template_key]);
    }

    public function retry(NotificationDelivery $delivery, User $user): NotificationDelivery
    {
        if ($delivery->status === 'failed') {
            $delivery->update(['status' => 'queued', 'queued_at' => now(), 'next_retry_at' => null, 'created_by' => $user->id]);
            SendNotificationDeliveryJob::dispatch($delivery->id);
            $this->auditLogger->record('email.retried', $delivery, 'Legacy email delivery retried', ['company_id' => $delivery->company_id]);

            return $delivery;
        }
        abort_unless($delivery->status === 'temporarily_failed', 422, 'Only temporary email delivery failures can be retried.');
        $delivery->update(['queued_at' => now(), 'next_retry_at' => null, 'created_by' => $user->id]);
        SendNotificationDeliveryJob::dispatch($delivery->id);
        $this->auditLogger->record('email.retried', $delivery, 'Email delivery retried', ['company_id' => $delivery->company_id]);

        return $delivery;
    }

    public function manualResend(NotificationDelivery $delivery, User $user): NotificationDelivery
    {
        abort_unless(in_array($delivery->status, ['temporarily_failed', 'permanently_failed'], true), 422, 'This email delivery cannot be resent.');

        $resent = DB::transaction(function () use ($delivery, $user): NotificationDelivery {
            $recent = NotificationDelivery::query()
                ->where('company_id', $delivery->company_id)
                ->where('related_type', $delivery->related_type)
                ->where('related_id', $delivery->related_id)
                ->where('template_key', $delivery->template_key)
                ->whereIn('status', ['queued', 'processing', 'sent'])
                ->where('created_at', '>=', now()->subMinutes(2))
                ->exists();
            if ($recent) {
                throw ValidationException::withMessages(['email' => 'A recent invoice email is already being delivered.']);
            }

            $resent = NotificationDelivery::query()->create([
                'company_id' => $delivery->company_id,
                'created_by' => $user->id,
                'related_type' => $delivery->related_type,
                'related_id' => $delivery->related_id,
                'event_key' => $delivery->event_key,
                'template_key' => $delivery->template_key,
                'channel' => 'email',
                'recipient' => $delivery->recipient,
                'recipient_name' => $delivery->recipient_name,
                'subject' => $delivery->subject,
                'status' => 'queued',
                'idempotency_key' => 'email-resend:'.hash('sha256', $delivery->id.'|'.Str::uuid()),
                'payload' => $delivery->payload,
                'queued_at' => now(),
            ]);
            $this->lifecycle->recordQueued($resent, 'delivery.manual_resend_queued', ['manual_resend_from' => $delivery->id]);
            $this->auditLogger->record('email.manual_resent', $resent, 'Invoice email manually resent', ['company_id' => $resent->company_id, 'original_delivery_id' => $delivery->id]);

            return $resent->refresh();
        });

        SendNotificationDeliveryJob::dispatch($resent->id);

        return $resent;
    }

    /** @param array{configured: bool, source: string, setting: ?CompanyEmailSetting, reason: ?string} $configuration */
    private function mailer(array $configuration): Mailer
    {
        if ($configuration['source'] !== 'company') {
            return Mail::mailer();
        }

        $setting = $configuration['setting'];
        config(['mail.mailers.company_smtp' => [
            'transport' => 'smtp',
            'host' => $setting->host,
            'port' => $setting->port,
            'username' => $setting->username,
            'password' => $setting->password,
            'scheme' => $setting->encryption === 'ssl' ? 'smtps' : 'smtp',
            'auto_tls' => $setting->encryption !== 'none',
            'require_tls' => $setting->encryption === 'tls',
        ]]);
        Mail::purge('company_smtp');

        return Mail::mailer('company_smtp');
    }

    /** @param array{configured: bool, source: string, setting: ?CompanyEmailSetting, reason: ?string} $configuration
     * @return array{address: ?string, name: ?string, reply_to: ?string}
     */
    private function sender(array $configuration): array
    {
        $setting = $configuration['setting'];

        return [
            'address' => $setting?->from_address ?: config('mail.from.address'),
            'name' => $setting?->from_name ?: config('mail.from.name'),
            'reply_to' => $setting?->reply_to_address,
        ];
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function safePayload(array $payload): array
    {
        return [
            'heading' => str((string) ($payload['heading'] ?? 'RetailPOS notification'))->limit(180)->toString(),
            'greeting' => str((string) ($payload['greeting'] ?? 'Hello,'))->limit(180)->toString(),
            'message' => str((string) ($payload['message'] ?? ''))->limit(4000)->toString(),
            'details' => collect($payload['details'] ?? [])->map(fn ($value) => str((string) $value)->limit(500)->toString())->all(),
            'action_url' => isset($payload['action_url']) && filter_var($payload['action_url'], FILTER_VALIDATE_URL) ? $payload['action_url'] : null,
            'action_label' => isset($payload['action_label']) ? str((string) $payload['action_label'])->limit(80)->toString() : null,
            'attachment_type' => ($payload['attachment_type'] ?? null) === InvoiceEmailAttachmentService::TYPE ? InvoiceEmailAttachmentService::TYPE : null,
        ];
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function safeSensitivePayload(array $payload): array
    {
        $allowed = array_intersect_key($payload, array_flip(['heading', 'greeting', 'message', 'details', 'action_url', 'action_label']));

        return array_intersect_key($this->safePayload($allowed), $allowed);
    }

    private function cleanAddress(string $address): string
    {
        return str($address)->replace(["\r", "\n"], '')->trim()->lower()->toString();
    }

    private function cleanSubject(string $subject): string
    {
        return str($subject)->replace(["\r", "\n"], ' ')->squish()->limit(180)->toString();
    }

    private function cleanReminderValue(?string $value, int $limit): ?string
    {
        $value = str((string) $value)->replace(["\r", "\n"], '')->squish()->lower()->limit($limit)->toString();

        return $value !== '' && preg_match('/\A[a-z_]+\z/', $value) ? $value : null;
    }
}
