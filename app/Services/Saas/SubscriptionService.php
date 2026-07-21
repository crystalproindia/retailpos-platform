<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use App\Events\Domain\Saas\SaasSubscriptionDomainEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EntitlementService $entitlements,
        private readonly DomainEventDispatcher $domainEvents,
    ) {
    }

    public function create(Company $company, SaasPlan $plan, ?User $actor, string $method = 'manual'): SaasSubscription
    {
        $subscription = DB::transaction(function () use ($company, $plan, $actor, $method): SaasSubscription {
            $active = SaasSubscription::query()
                ->where('company_id', $company->id)
                ->whereIn('status', ['trialing', 'active', 'grace_period', 'past_due', 'suspended'])
                ->lockForUpdate()
                ->first();

            if ($active) {
                throw ValidationException::withMessages(['company' => 'Tenant already has a current subscription.']);
            }

            $plan->loadMissing(['features', 'limits']);
            $snapshot = $plan->snapshot();
            $trial = $plan->trial_days > 0;
            $today = today();

            $subscription = SaasSubscription::create([
                'company_id' => $company->id,
                'saas_plan_id' => $plan->id,
                'subscription_number' => 'SUB-'.strtoupper(Str::ulid()),
                'status' => $trial ? 'trialing' : 'active',
                'billing_interval' => $plan->billing_interval,
                'currency' => $plan->currency,
                'price_snapshot' => $plan->base_price ?? 0,
                'tax_snapshot' => $plan->tax_percentage ?? 0,
                'setup_fee_snapshot' => $plan->setup_fee ?? 0,
                'feature_snapshot' => $snapshot['features'],
                'limit_snapshot' => $snapshot['limits'],
                'trial_starts_at' => $trial ? $today : null,
                'trial_ends_at' => $trial ? $today->copy()->addDays($plan->trial_days) : null,
                'starts_at' => $today,
                'current_period_starts_at' => $today,
                'current_period_ends_at' => $this->periodEnd($today, $plan->billing_interval),
                'renewal_date' => $this->periodEnd($today, $plan->billing_interval),
                'grace_period_ends_at' => $trial ? $today->copy()->addDays($plan->trial_days + $plan->grace_period_days) : null,
                'billing_method' => $method,
            ]);

            $this->event($subscription, $trial ? 'TrialStarted' : 'SubscriptionActivated', null, $subscription->status, $actor);
            $this->audit->record('saas.subscription.created', $subscription, 'SaaS subscription created.');

            return $subscription;
        });

        $this->entitlements->clear($company);

        return $subscription;
    }

    public function transition(SaasSubscription $subscription, string $status, ?User $actor, ?string $reason = null, ?string $idempotencyKey = null): SaasSubscription
    {
        $updated = DB::transaction(function () use ($subscription, $status, $actor, $reason, $idempotencyKey): SaasSubscription {
            $subscription = SaasSubscription::lockForUpdate()->findOrFail($subscription->id);
            $from = $subscription->status;
            if ($from === $status) {
                return $subscription;
            }

            if (! $this->canTransition($from, $status)) {
                throw ValidationException::withMessages(['status' => 'This subscription lifecycle change is not allowed.']);
            }

            $subscription->update([
                'status' => $status,
                'suspended_at' => $status === 'suspended' ? now() : $subscription->suspended_at,
                'cancelled_at' => $status === 'cancelled' ? now() : $subscription->cancelled_at,
                'reactivated_at' => $status === 'active' && $from === 'suspended' ? now() : $subscription->reactivated_at,
            ]);

            $this->event($subscription, 'Subscription'.str($status)->headline()->replace(' ', ''), $from, $status, $actor, ['reason' => $reason], $idempotencyKey);
            $this->audit->record('saas.subscription.transitioned', $subscription, 'Subscription status changed.', [
                'from' => $from,
                'to' => $status,
                'reason' => $reason,
            ]);

            return $subscription->refresh();
        });

        $this->entitlements->clear($updated->company);

        return $updated;
    }

    public function renew(SaasSubscription $subscription, ?User $actor, string $method, ?string $reference = null, ?string $idempotencyKey = null): SaasSubscription
    {
        $renewed = DB::transaction(function () use ($subscription, $actor, $method, $reference, $idempotencyKey): SaasSubscription {
            $subscription = SaasSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if ($idempotencyKey && $subscription->events()->where('idempotency_key', $idempotencyKey)->exists()) {
                return $subscription;
            }
            $start = max(today(), $subscription->current_period_ends_at?->copy() ?? today());
            $end = $this->periodEnd($start, $subscription->billing_interval);
            $from = $subscription->status;

            $subscription->update([
                'status' => 'active',
                'billing_method' => $method,
                'provider_reference' => $reference,
                'current_period_starts_at' => $start,
                'current_period_ends_at' => $end,
                'renewal_date' => $end,
                'grace_period_ends_at' => null,
                'cancellation_effective_at' => null,
                'cancelled_at' => null,
                'reactivated_at' => $from === 'suspended' ? now() : $subscription->reactivated_at,
            ]);

            $this->event($subscription, 'SubscriptionRenewed', $from, 'active', $actor, ['billing_method' => $method, 'reference' => $reference], $idempotencyKey);
            $this->audit->record('saas.subscription.renewed', $subscription, 'Subscription renewed.', ['method' => $method]);

            return $subscription->refresh();
        });

        $this->entitlements->clear($renewed->company);

        return $renewed;
    }

    public function extendTrial(SaasSubscription $subscription, ?User $actor, int $days, string $reason, string $idempotencyKey): SaasSubscription
    {
        if ($days < 1 || $days > 365) {
            throw ValidationException::withMessages(['days' => 'Trial extension must be between 1 and 365 days.']);
        }

        $extended = DB::transaction(function () use ($subscription, $actor, $days, $reason, $idempotencyKey): SaasSubscription {
            $subscription = SaasSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if ($subscription->events()->where('idempotency_key', $idempotencyKey)->exists()) return $subscription;
            if (! in_array($subscription->status, ['trialing', 'grace_period', 'expired'], true)) throw ValidationException::withMessages(['subscription' => 'Only trial subscriptions can be extended.']);
            $end = max(today(), $subscription->trial_ends_at?->copy() ?? today())->addDays($days);
            $from = $subscription->status;
            $subscription->update(['status' => 'trialing', 'trial_ends_at' => $end, 'grace_period_ends_at' => $end->copy()->addDays((int) ($subscription->plan?->grace_period_days ?? 0))]);
            $this->event($subscription, 'TrialExtended', $from, 'trialing', $actor, ['days' => $days, 'reason' => $reason], $idempotencyKey);
            $this->audit->record('saas.trial.extended', $subscription, 'Trial extended.', ['days' => $days, 'reason' => $reason]);
            return $subscription->refresh();
        });

        $this->entitlements->clear($extended->company);

        return $extended;
    }

    public function requestChange(SaasSubscription $subscription, User $actor, string $event, array $payload): void
    {
        $this->event($subscription, $event, $subscription->status, $subscription->status, $actor, $payload);
        $this->audit->record('saas.subscription.requested', $subscription, 'Subscription change requested.', ['event' => $event] + $payload);
    }

    public function recordReminder(SaasSubscription $subscription, string $event, string $idempotencyKey): void
    {
        $this->event($subscription, $event, $subscription->status, $subscription->status, null, [
            'renewal_date' => $subscription->renewal_date?->toDateString(),
            'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
        ], $idempotencyKey);
    }

    public function scheduleCancellation(SaasSubscription $subscription, User $actor, bool $immediate, ?string $reason = null): SaasSubscription
    {
        if ($immediate) {
            return $this->transition($subscription, 'cancelled', $actor, $reason);
        }

        $subscription->update(['cancellation_effective_at' => $subscription->current_period_ends_at]);
        $this->event($subscription, 'SubscriptionCancellationScheduled', $subscription->status, $subscription->status, $actor, ['reason' => $reason]);
        $this->audit->record('saas.subscription.cancellation_scheduled', $subscription, 'Subscription cancellation scheduled.');

        return $subscription->refresh();
    }

    private function periodEnd($start, string $interval)
    {
        return match ($interval) {
            'quarterly' => $start->copy()->addMonths(3),
            'half-yearly' => $start->copy()->addMonths(6),
            'yearly' => $start->copy()->addYear(),
            default => $start->copy()->addMonth(),
        };
    }

    private function event(SaasSubscription $subscription, string $key, ?string $from, string $to, ?User $actor, array $payload = [], ?string $idempotencyKey = null): void
    {
        $attributes = [
            'event_key' => $key,
            'from_status' => $from,
            'to_status' => $to,
            'payload' => $payload,
            'actor_id' => $actor?->id,
        ];
        $event = $idempotencyKey
            ? $subscription->events()->firstOrCreate(['idempotency_key' => $idempotencyKey], $attributes)
            : $subscription->events()->create($attributes);

        if (! $event->wasRecentlyCreated) return;
        $eventKey = preg_replace('/(?<!^)([A-Z])/', '.$1', $key) ?? $key;
        $eventKey = preg_replace('/(?<=\D)(?=\d)|(?<=\d)(?=\D)/', '.', $eventKey) ?? $eventKey;
        $this->domainEvents->dispatch(new SaasSubscriptionDomainEvent('saas.'.str($eventKey)->lower(), $subscription->company_id, $actor?->id, $subscription->id, $payload + ['subscription_number' => $subscription->subscription_number], $idempotencyKey));
    }

    private function canTransition(string $from, string $to): bool
    {
        return in_array($to, match ($from) {
            'trialing' => ['active', 'grace_period', 'expired', 'cancelled', 'suspended'],
            'active' => ['grace_period', 'past_due', 'suspended', 'cancelled'],
            'grace_period' => ['active', 'past_due', 'suspended', 'expired', 'cancelled'],
            'past_due' => ['active', 'grace_period', 'suspended', 'expired', 'cancelled'],
            'suspended' => ['active', 'cancelled', 'expired'],
            'cancelled', 'expired' => ['active'],
            default => [],
        }, true);
    }
}
