<?php

namespace App\Services\Notifications;

use App\Jobs\Notifications\SendNotificationDeliveryJob;
use App\Mail\CommandCenterEmail;
use App\Models\CompanyEmailSetting;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Repositories\Integrations\CompanyEmailSettingsRepository;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

class EmailDeliveryService
{
    public function __construct(
        private readonly CompanyEmailSettingsRepository $settings,
        private readonly AuditLogger $auditLogger,
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
    ): NotificationDelivery {
        $recipient = $this->cleanAddress($recipient);
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
                    'channel' => 'email',
                    'recipient' => $recipient,
                    'recipient_name' => $recipientName,
                    'subject' => $this->cleanSubject($subject),
                    'status' => $configuration['configured'] ? 'queued' : 'skipped_not_configured',
                    'queued_at' => $configuration['configured'] ? now() : null,
                    'payload' => $this->safePayload($payload),
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

        $delivery->update([
            'status' => 'sending',
            'attempt_count' => $delivery->attempt_count + 1,
            'sent_at' => now(),
            'failure_reason' => null,
        ]);

        $payload = $delivery->payload ?? [];
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
        ));

        $delivery->update(['status' => 'sent', 'delivered_at' => now(), 'failure_reason' => null]);
        $this->auditLogger->record('email.sent', $delivery, 'Email delivery sent', ['company_id' => $delivery->company_id, 'template_key' => $delivery->template_key]);
    }

    public function retry(NotificationDelivery $delivery, User $user): NotificationDelivery
    {
        $delivery->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'next_retry_at' => null, 'failure_reason' => null, 'created_by' => $user->id]);
        SendNotificationDeliveryJob::dispatch($delivery->id);
        $this->auditLogger->record('email.retried', $delivery, 'Email delivery retried', ['company_id' => $delivery->company_id]);

        return $delivery;
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
        ];
    }

    private function cleanAddress(string $address): string
    {
        return str($address)->replace(["\r", "\n"], '')->trim()->lower()->toString();
    }

    private function cleanSubject(string $subject): string
    {
        return str($subject)->replace(["\r", "\n"], ' ')->squish()->limit(180)->toString();
    }
}
