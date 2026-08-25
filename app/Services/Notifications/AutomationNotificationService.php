<?php

namespace App\Services\Notifications;

use App\Models\Company;
use App\Models\NotificationAutomationSetting;
use App\Models\NotificationConditionState;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutomationNotificationService
{
    public function __construct(
        private readonly AutomationRecipientResolver $recipients,
        private readonly EmailDeliveryService $emails,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $conditions
     * @return array{created:int,recovered:int}
     */
    public function sync(Company $company, NotificationAutomationSetting $settings, string $type, array $conditions): array
    {
        $seen = [];
        $created = 0;

        foreach ($conditions as $condition) {
            $key = $this->key($condition);
            $seen[] = $key;
            [$state, $notify] = $this->activate($company->id, $type, $condition);
            if (! $notify) {
                continue;
            }

            $created += $this->deliver($company, $settings, $state, $condition);
        }

        $active = NotificationConditionState::query()
            ->where('company_id', $company->id)
            ->where('condition_type', $type)
            ->where('is_active', true)
            ->get();
        $recovered = 0;
        foreach ($active as $state) {
            if (in_array($this->stateKey($state), $seen, true)) {
                continue;
            }
            $state->update(['is_active' => false, 'recovered_at' => now(), 'last_detected_at' => now()]);
            $recovered++;
        }

        return compact('created', 'recovered');
    }

    /** @param array<string, mixed> $condition @return array{NotificationConditionState,bool} */
    private function activate(int $companyId, string $type, array $condition): array
    {
        return DB::transaction(function () use ($companyId, $type, $condition): array {
            $identity = [
                'company_id' => $companyId,
                'condition_type' => $type,
                'subject_type' => (string) $condition['subject_type'],
                'subject_id' => (int) $condition['subject_id'],
                'stage' => (string) $condition['stage'],
            ];
            $state = NotificationConditionState::query()->where($identity)->lockForUpdate()->first();
            if (! $state) {
                return [NotificationConditionState::query()->create($identity + [
                    'branch_id' => $condition['branch_id'] ?? null,
                    'severity' => $condition['severity'] ?? 'attention',
                    'is_active' => true,
                    'cycle' => 1,
                    'context' => $this->safeContext($condition['context'] ?? []),
                    'first_detected_at' => now(),
                    'last_detected_at' => now(),
                ]), true];
            }

            $notify = ! $state->is_active || $this->severityRank((string) ($condition['severity'] ?? 'attention')) > $this->severityRank($state->severity);
            $state->update([
                'branch_id' => $condition['branch_id'] ?? null,
                'severity' => $condition['severity'] ?? 'attention',
                'is_active' => true,
                'cycle' => $notify ? $state->cycle + 1 : $state->cycle,
                'context' => $this->safeContext($condition['context'] ?? []),
                'last_detected_at' => now(),
                'recovered_at' => null,
            ]);

            return [$state->refresh(), $notify];
        });
    }

    /** @param array<string, mixed> $condition */
    private function deliver(Company $company, NotificationAutomationSetting $settings, NotificationConditionState $state, array $condition): int
    {
        $recipients = $this->recipients->internalRecipients(
            $company->id,
            $state->branch_id,
            (bool) ($condition['administrators_only'] ?? false),
        );
        $created = 0;

        foreach ($recipients as $recipient) {
            $baseKey = 'automation:'.$state->id.':'.$state->cycle.':'.$recipient->id;
            if ($this->createInApp($recipient, $state, $condition, $baseKey.':database')) {
                $created++;
            }
            if ($settings->internal_email_enabled && filled($recipient->email)) {
                $this->queueEmail($recipient->email, $recipient->name, $company, $state, $condition, $baseKey.':email');
            }
        }

        if ($settings->customer_payment_emails_enabled && filled($condition['customer_email'] ?? null)) {
            $this->queueEmail(
                (string) $condition['customer_email'],
                $condition['customer_name'] ?? null,
                $company,
                $state,
                $condition,
                'automation:'.$state->id.':'.$state->cycle.':customer:email',
            );
        }

        $state->update(['last_notified_at' => now()]);

        return $created;
    }

    /** @param array<string, mixed> $condition */
    private function createInApp(User $recipient, NotificationConditionState $state, array $condition, string $idempotencyKey): bool
    {
        return DB::transaction(function () use ($recipient, $state, $condition, $idempotencyKey): bool {
            $notificationId = (string) Str::uuid();
            $delivery = NotificationDelivery::query()->firstOrCreate(
                ['company_id' => $recipient->company_id, 'idempotency_key' => $idempotencyKey],
                [
                    'user_id' => $recipient->id,
                    'notification_id' => $notificationId,
                    'related_type' => $state->subject_type,
                    'related_id' => $state->subject_id,
                    'event_key' => 'automation.'.$state->condition_type,
                    'template_key' => 'automation_'.$state->condition_type,
                    'reminder_stage' => str($state->stage)->limit(32)->toString(),
                    'reminder_source' => 'automation',
                    'channel' => 'database',
                    'recipient' => (string) $recipient->id,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'payload' => $this->safeContext($condition['context'] ?? []),
                ],
            );
            if (! $delivery->wasRecentlyCreated) {
                return false;
            }

            $notification = new PlatformNotification(
                channel: 'database',
                eventKey: 'automation.'.$state->condition_type,
                title: (string) $condition['title'],
                message: (string) $condition['message'],
                actionUrl: $condition['action_url'] ?? null,
                severity: $condition['severity'] ?? 'attention',
                icon: $condition['icon'] ?? null,
                aggregateType: $state->subject_type,
                aggregateId: $state->subject_id,
                metadata: ['category' => $condition['category'] ?? 'system', 'stage' => $state->stage],
            );
            DB::table('notifications')->insert([
                'id' => $notificationId,
                'type' => PlatformNotification::class,
                'notifiable_type' => $recipient->getMorphClass(),
                'notifiable_id' => $recipient->id,
                'data' => json_encode($notification->toDatabase($recipient), JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    /** @param array<string, mixed> $condition */
    private function queueEmail(string $email, ?string $name, Company $company, NotificationConditionState $state, array $condition, string $idempotencyKey): void
    {
        $this->emails->queue(
            companyId: $company->id,
            recipient: $email,
            subject: (string) $condition['title'],
            templateKey: 'automation_'.$state->condition_type,
            payload: [
                'heading' => $condition['title'],
                'greeting' => $name ? 'Hello '.$name.',' : 'Hello,',
                'message' => $condition['message'],
                'details' => $condition['email_details'] ?? [],
                'action_url' => $condition['action_url'] ?? null,
                'action_label' => $condition['action_label'] ?? 'Open RetailPOS',
            ],
            related: $state,
            idempotencyKey: $idempotencyKey,
            recipientName: $name,
            reminderStage: str($state->stage)->limit(32)->toString(),
            reminderSource: 'automation',
        );
    }

    /** @param array<string, mixed> $condition */
    private function key(array $condition): string
    {
        return implode('|', [$condition['subject_type'], $condition['subject_id'], $condition['stage']]);
    }

    private function stateKey(NotificationConditionState $state): string
    {
        return implode('|', [$state->subject_type, $state->subject_id, $state->stage]);
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function safeContext(array $context): array
    {
        return collect($context)->take(20)->map(function ($value) {
            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                return $value;
            }

            return str((string) $value)->limit(500)->toString();
        })->all();
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'important' => 3,
            'attention' => 2,
            default => 1,
        };
    }
}
